<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PluginRuntimeGate
{
    /** @var array<string, array{allowed: bool, reason: string}> */
    private array $inspections = [];

    /** @var array<string, bool>|null */
    private ?array $runtimeStates = null;

    /** @var array<string, true>|null */
    private ?array $activePlugins = null;

    private ?bool $registryAvailable = null;

    public function __construct(
        private readonly PluginFilesystem $filesystem,
        private readonly PlatformVersion $platformVersion,
    ) {}

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function inspect(string $slug): array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return ['allowed' => false, 'reason' => 'plugin_slug_missing'];
        }

        if (isset($this->inspections[$slug])) {
            return $this->inspections[$slug];
        }

        if (! $this->hasPluginRegistry()) {
            return $this->remember($slug, false, 'plugin_registry_unavailable');
        }

        try {
            $plugin = Plugin::query()->where('slug', $slug)->first();
        } catch (Throwable) {
            return $this->remember($slug, false, 'plugin_registry_error');
        }

        if (! $plugin) {
            return $this->remember($slug, false, 'plugin_not_installed');
        }

        if ($plugin->status !== Plugin::STATUS_ACTIVE) {
            return $this->remember($slug, false, 'plugin_disabled');
        }

        if (! $this->runtimeEnabled($slug)) {
            return $this->remember($slug, false, 'plugin_runtime_disabled');
        }

        if ($this->filesystem->path($plugin) === null) {
            return $this->remember($slug, false, 'plugin_module_missing');
        }

        $platformConstraint = data_get($this->filesystem->manifest($plugin), 'platform_version');
        if (is_string($platformConstraint) && ! $this->platformVersion->supports($platformConstraint)) {
            return $this->remember($slug, false, 'plugin_platform_incompatible');
        }

        $missingDependency = $this->missingDependency($plugin);
        if ($missingDependency !== null) {
            return $this->remember($slug, false, 'plugin_dependency_disabled:'.$missingDependency);
        }

        return $this->remember($slug, true, 'plugin_enabled');
    }

    public function allows(string $slug): bool
    {
        return $this->inspect($slug)['allowed'];
    }

    public function flush(?string $slug = null): void
    {
        if ($slug === null) {
            $this->inspections = [];
            $this->runtimeStates = null;
            $this->activePlugins = null;
            $this->registryAvailable = null;
            $this->filesystem->flush();

            return;
        }

        unset($this->inspections[$slug]);
        $this->runtimeStates = null;
        $this->activePlugins = null;
        $this->filesystem->flush($slug);
    }

    private function runtimeEnabled(string $slug): bool
    {
        if (! Schema::hasTable('platform_plugin_registry_entries')) {
            return true;
        }

        if ($this->runtimeStates === null) {
            $this->runtimeStates = DB::table('platform_plugin_registry_entries')
                ->where('registry_type', 'runtime')
                ->get(['plugin_slug', 'payload'])
                ->mapWithKeys(function (object $entry): array {
                    $payload = json_decode((string) $entry->payload, true);

                    return [(string) $entry->plugin_slug => (bool) data_get($payload, 'runtime_enabled', true)];
                })
                ->all();
        }

        return $this->runtimeStates[$slug] ?? true;
    }

    private function missingDependency(Plugin $plugin): ?string
    {
        $dependencies = $plugin->dependencies;

        if (! is_array($dependencies)) {
            $dependencies = [];
        }

        $manifestDependencies = data_get($this->filesystem->manifest($plugin), 'dependencies', []);
        if (is_array($manifestDependencies)) {
            $dependencies = array_merge($dependencies, $manifestDependencies);
        }

        $activePlugins = $this->activePluginSlugs();

        foreach (array_unique(array_filter($dependencies, 'is_string')) as $dependency) {
            $dependency = trim($dependency);

            if ($dependency === '') {
                continue;
            }

            if (! isset($activePlugins[$dependency]) || ! $this->runtimeEnabled($dependency)) {
                return $dependency;
            }
        }

        return null;
    }

    /** @return array<string, true> */
    private function activePluginSlugs(): array
    {
        if ($this->activePlugins === null) {
            $this->activePlugins = Plugin::query()
                ->active()
                ->pluck('slug')
                ->mapWithKeys(fn (string $slug): array => [$slug => true])
                ->all();
        }

        return $this->activePlugins;
    }

    private function hasPluginRegistry(): bool
    {
        return $this->registryAvailable ??= Schema::hasTable('plugins');
    }

    /** @return array{allowed: bool, reason: string} */
    private function remember(string $slug, bool $allowed, string $reason): array
    {
        return $this->inspections[$slug] = compact('allowed', 'reason');
    }
}
