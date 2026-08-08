<?php

namespace App\Platform\Core\Updates;

use App\Platform\Core\Backups\BackupManager;
use App\Platform\Core\Backups\StepBackupper;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Licensing\LicenseManager;
use App\Platform\Core\Logs\FailedOperationLogger;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Models\PluginUpdate;
use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\PluginAssetPublisher;
use App\Platform\Core\Services\PluginCacheCleaner;
use App\Platform\Core\Services\PluginMigrationRunner;
use App\Platform\Core\Services\PluginSeederRunner;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class UpdateRunner
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginUpdateChecker $pluginUpdates,
        private readonly PluginMigrationRunner $migrations,
        private readonly PluginSeederRunner $seeders,
        private readonly PluginAssetPublisher $pluginAssets,
        private readonly PluginCacheCleaner $cache,
        private readonly FailedUpdateHandler $failures,
        private readonly LicenseManager $licenses,
        private readonly BackupManager $backups,
        private readonly OperationLogger $operations,
        private readonly FailedOperationLogger $failedOperations,
        private readonly StepBackupper $stepBackups,
    ) {
        //
    }

    public function updatePlugin(string $slug, bool $allowInactive = false): UpdateResult
    {
        $plugin = $this->plugins->findBySlug($slug);

        if (! $plugin) {
            return UpdateResult::failure('plugin', $slug, null, null, 'resolve', 'Plugin is not installed.');
        }

        if (! $allowInactive && $plugin->status !== Plugin::STATUS_ACTIVE) {
            return UpdateResult::failure('plugin', $slug, (string) $plugin->version, null, 'guard', 'Plugin is not active; inactive plugins are not updated by default.');
        }

        if (! $this->licenses->canUpdatePlugin($plugin->slug)) {
            return UpdateResult::failure('plugin', $slug, (string) $plugin->version, null, 'license', 'Plugin requires a valid license before update.');
        }

        $check = $this->pluginUpdates->checkPlugin($plugin);

        if (! $check['update_available']) {
            return UpdateResult::noUpdate('plugin', $slug, (string) $plugin->version);
        }

        $checkpoint = $this->checkpoint('plugin', $plugin->slug, [
            'plugin' => $plugin->getAttributes(),
            'update' => $check,
        ]);
        $operation = $this->operations->start('plugin.update', 'plugin', $plugin->slug, [
            'from_version' => $check['current_version'],
            'to_version' => $check['available_version'],
        ]);
        $backup = $this->backups->createCheckpoint('plugin.update', 'plugin', $plugin->slug, $checkpoint['data'], 'plugin-update');
        $targetVersion = (string) $check['available_version'];

        try {
            $this->runPluginSteps($plugin, $check['metadata'] ?? []);

            $manifest = $plugin->manifest ?? [];
            $manifest['version'] = $targetVersion;

            $plugin->forceFill([
                'version' => $targetVersion,
                'manifest' => $manifest,
            ])->save();
            $this->checkpointStep('plugin.update', 'plugin', $plugin->slug, 'version_record_updated', [
                'to_version' => $targetVersion,
            ]);

            if ($check['record_id']) {
                PluginUpdate::query()
                    ->whereKey($check['record_id'])
                    ->update([
                        'installed_at' => now(),
                        'executed_at' => now(),
                    ]);
                $this->checkpointStep('plugin.update', 'plugin', $plugin->slug, 'update_record_marked_installed', [
                    'record_id' => $check['record_id'],
                ]);
            }

            $this->cache->clear();
            $this->checkpointStep('plugin.update', 'plugin', $plugin->slug, 'cache_cleared');
            $this->backups->markCheckpointCompleted($backup);
            $this->operations->success($operation, 'Plugin update completed.', [
                'checkpoint_path' => $checkpoint['path'],
            ]);

            return UpdateResult::success('plugin', $slug, $check['current_version'], $targetVersion, 'complete', 'Plugin update completed.', $checkpoint['path']);
        } catch (Throwable $exception) {
            $this->backups->markCheckpointFailed($backup);
            $this->failedOperations->log($operation, $exception, [
                'checkpoint_path' => $checkpoint['path'],
            ]);

            return $this->failures->handle(
                $exception,
                UpdateResult::failure('plugin', $slug, $check['current_version'], $targetVersion, 'run_steps', $exception->getMessage(), $checkpoint['path']),
                $checkpoint,
                function () use ($plugin, $checkpoint): void {
                    $plugin->forceFill($checkpoint['data']['plugin'])->save();
                },
            );
        }
    }

    public function updateTheme(string $slug, bool $allowInactive = true): UpdateResult
    {
        return UpdateResult::failure('theme', $slug, null, null, 'disabled', 'Theme updates are provided by the Theme Manager plugin.');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function runPluginSteps(Plugin $plugin, array $metadata): void
    {
        foreach ($this->steps($metadata) as $step) {
            $type = $this->stepType($step);

            match ($type) {
                'migrations' => $this->migrations->run((string) $plugin->path, $this->pluginManifest($plugin)),
                'seeders' => $this->seeders->run($this->pluginManifest($plugin)),
                'assets' => $this->pluginAssets->publish((string) $plugin->path, $this->pluginManifest($plugin)),
                'clear_cache' => $this->cache->clear(),
                'fail' => throw new RuntimeException((string) data_get($step, 'message', 'Update step failed.')),
                default => throw new RuntimeException("Unsupported plugin update step [{$type}]."),
            };
            $this->checkpointStep('plugin.update', 'plugin', $plugin->slug, 'step_'.$type);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<int, mixed>
     */
    private function steps(array $metadata): array
    {
        $steps = data_get($metadata, 'steps', []);

        return is_array($steps) ? array_values($steps) : [];
    }

    private function stepType(mixed $step): string
    {
        if (is_string($step)) {
            return $step;
        }

        return is_array($step) ? (string) ($step['type'] ?? '') : '';
    }

    private function pluginManifest(Plugin $plugin): PluginManifest
    {
        return new PluginManifest(
            name: $plugin->name,
            slug: $plugin->slug,
            version: (string) $plugin->version,
            provider: (string) $plugin->provider,
            description: $plugin->description,
            author: $plugin->author,
            dependencies: $plugin->dependencies ?? [],
            manifest: $plugin->manifest ?? [],
            sourcePath: $plugin->path,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{path: string, data: array<string, mixed>}
     */
    private function checkpoint(string $type, string $slug, array $data): array
    {
        $directory = storage_path('app/platform/update-checkpoints');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.now()->format('YmdHis').'-'.$type.'-'.$slug.'.json';
        File::put($path, json_encode([
            'type' => $type,
            'slug' => $slug,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ['path' => $path, 'data' => $data];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function checkpointStep(string $operationType, string $targetType, string $targetSlug, string $step, array $metadata = []): void
    {
        $this->stepBackups->afterStep($operationType, $targetType, $targetSlug, $step, $metadata);
    }
}
