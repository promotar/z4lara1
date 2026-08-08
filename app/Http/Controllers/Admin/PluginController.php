<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Backups\StepBackupper;
use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PluginManager;
use App\Platform\Core\Services\PluginUploadWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;
use ZipArchive;

class PluginController extends Controller
{
    private const MAX_PLUGIN_ARCHIVE_FILES = 5000;

    private const MAX_PLUGIN_EXTRACTED_BYTES = 209715200;

    public function index(): View
    {
        $plugins = Plugin::query()
            ->latest('created_at')
            ->get()
            ->each(function (Plugin $plugin): void {
                $plugin->setAttribute('admin_settings_link', $this->settingsLink($plugin));
            });

        return view('admin.plugins.index', [
            'plugins' => $plugins,
        ]);
    }

    public function create(): View
    {
        return view('admin.plugins.create');
    }

    public function store(
        Request $request,
        PluginManager $plugins,
        PluginUploadWorkspace $uploads,
    ): RedirectResponse {
        $zipPath = null;
        $extractDirectory = null;
        $movedInstallPath = null;

        $request->validate([
            'plugin_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        try {
            $zipPath = $uploads->store($request->file('plugin_zip'));
            $absoluteZipPath = $uploads->absolutePath($zipPath);
            $extractDirectory = $uploads->createExtractionDirectory('plugin');
            $extractPath = $uploads->absolutePath($extractDirectory);

            $pluginRoot = $this->extractAndValidateZip($absoluteZipPath, $extractPath);
            $manifest = $plugins->validatePackage($pluginRoot);
            $slug = $manifest->slug;
            $installedPath = base_path('modules/'.$slug);

            if (File::exists($installedPath)) {
                $existing = $plugins->findBySlug($slug);

                if (! $existing) {
                    throw new RuntimeException('A module directory with this slug already exists, but no plugin registry record was found. Register it or remove the directory before uploading an update.');
                }

                $this->assertSamePluginIdentity($existing, $manifest);

                $token = $this->storePendingUpdate(
                    $request,
                    $uploads,
                    $zipPath,
                    $existing,
                    $manifest,
                    $installedPath,
                    $pluginRoot,
                );

                return redirect()->route('admin.plugins.update.review', $token);
            }

            File::ensureDirectoryExists(base_path('modules'));
            $this->relocateDirectory($pluginRoot, $installedPath);
            $movedInstallPath = $installedPath;
            $this->checkpointStep($request, 'plugin.upload.install', $slug, 'files_moved', [
                'installed_path' => $installedPath,
            ]);

            $plugin = $plugins->update($installedPath);
            $this->checkpointStep($request, 'plugin.upload.install', $slug, 'plugin_manager_install_completed', [
                'status' => $plugin->status,
            ]);
        } catch (\Throwable $exception) {
            if (
                is_string($movedInstallPath)
                && File::isDirectory($movedInstallPath)
                && ! $plugins->findBySlug(basename($movedInstallPath))
            ) {
                File::deleteDirectory($movedInstallPath);
            }

            return back()->withErrors(['plugin_zip' => $exception->getMessage()]);
        } finally {
            $uploads->discardDirectory($extractDirectory);
            $uploads->discardFile($zipPath);
        }

        return redirect()->route('admin.plugins.index')->with('status', 'Plugin installed successfully through PluginManager.');
    }

    public function reviewUpdate(string $token): View|RedirectResponse
    {
        $pending = $this->pendingUpdate($token);

        if (! $pending) {
            return redirect()
                ->route('admin.plugins.create')
                ->withErrors(['plugin_zip' => 'The pending plugin update expired. Upload the plugin ZIP again.']);
        }

        return view('admin.plugins.update', [
            'token' => $token,
            'pending' => $pending,
        ]);
    }

    public function confirmUpdate(
        string $token,
        Request $request,
        PluginManager $plugins,
        PluginUploadWorkspace $uploads,
    ): RedirectResponse {
        $pending = $this->pendingUpdate($token);

        if (! $pending) {
            return redirect()
                ->route('admin.plugins.create')
                ->withErrors(['plugin_zip' => 'The pending plugin update expired. Upload the plugin ZIP again.']);
        }

        $zipPath = (string) ($pending['zip_path'] ?? '');

        if ($zipPath === '' || ! $uploads->exists($zipPath)) {
            $this->forgetPendingUpdate($token);

            return redirect()
                ->route('admin.plugins.create')
                ->withErrors(['plugin_zip' => 'The pending plugin ZIP was not found. Upload the plugin ZIP again.']);
        }

        $extractDirectory = null;

        try {
            $absoluteZipPath = $uploads->absolutePath($zipPath);
            $extractDirectory = $uploads->createExtractionDirectory('plugin-update');
            $extractPath = $uploads->absolutePath($extractDirectory);
            $pluginRoot = $this->extractAndValidateZip($absoluteZipPath, $extractPath);
            $manifest = $plugins->validatePackage($pluginRoot);
            $existing = $plugins->findBySlug($manifest->slug);

            if (! $existing) {
                throw new RuntimeException('The existing plugin registry record was not found. Upload the plugin ZIP again after confirming the plugin is installed.');
            }

            $installedPath = base_path('modules/'.$manifest->slug);

            if (! File::exists($installedPath)) {
                throw new RuntimeException('The existing plugin module directory was not found. Upload the plugin ZIP as a fresh install instead.');
            }

            $this->assertSamePluginIdentity($existing, $manifest);

            $plugin = $this->replaceInstalledPlugin($request, $plugins, $existing, $manifest, $pluginRoot, $installedPath);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.plugins.update.review', $token)
                ->withErrors(['plugin_zip' => $exception->getMessage()]);
        } finally {
            $uploads->discardDirectory($extractDirectory);
        }

        $uploads->discardFile($zipPath);
        $this->forgetPendingUpdate($token);

        return redirect()
            ->route('admin.plugins.index')
            ->with('status', "Plugin [{$plugin->slug}] updated from version {$pending['old']['version']} to {$plugin->version}.");
    }

    public function activate(string $slug, PluginManager $plugins): RedirectResponse
    {
        try {
            $plugins->activate($slug);
            $this->checkpointStep(request(), 'plugin.activate', $slug, 'status_marked_active');
        } catch (\Throwable $exception) {
            return back()->withErrors(['plugin' => $exception->getMessage()]);
        }

        return back()->with('status', 'Plugin activated successfully.');
    }

    public function deactivate(string $slug, PluginManager $plugins): RedirectResponse
    {
        try {
            $plugins->deactivate($slug);
            $this->checkpointStep(request(), 'plugin.disable', $slug, 'status_marked_inactive');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['plugin' => $exception->getMessage()]);
        }

        return back()->with('status', 'Plugin deactivated successfully and remains installed.');
    }

    public function destroy(string $slug, PluginManager $plugins): RedirectResponse
    {
        request()->validate([
            'purge_confirmation' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($slug): void {
                if (! hash_equals($slug, (string) $value)) {
                    $fail('The plugin slug confirmation does not match.');
                }
            }],
        ]);

        $result = $plugins->purge($slug);

        if (! ($result['success'] ?? false)) {
            return back()->withErrors([
                'plugin' => $result['message'] ?? 'Plugin uninstall failed.',
            ]);
        }

        return redirect()
            ->route('admin.plugins.index')
            ->with('status', $result['message'] ?? 'Plugin and all owned data were permanently deleted.');
    }

    private function extractAndValidateZip(string $absoluteZipPath, string $extractPath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($absoluteZipPath) !== true) {
            throw new RuntimeException('Unable to open the uploaded ZIP file.');
        }

        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_PLUGIN_ARCHIVE_FILES) {
            $zip->close();

            throw new RuntimeException(
                'The ZIP file must contain between 1 and '.self::MAX_PLUGIN_ARCHIVE_FILES.' entries.',
            );
        }

        $extractedBytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);
            $statistics = $zip->statIndex($i);
            $extractedBytes += is_array($statistics) ? (int) ($statistics['size'] ?? 0) : 0;

            if (
                strlen($normalized) > 512 ||
                str_contains($normalized, "\0") ||
                str_contains($normalized, '../') ||
                str_starts_with($normalized, '/') ||
                preg_match('/^[A-Za-z]:\//', $normalized) ||
                preg_match('/(^|\/)(\.env|\.git|vendor|node_modules)(\/|$)/i', $normalized) ||
                preg_match('/\.(env|exe|bat|cmd|sh|phtml|phar|pem|key)$/i', $normalized)
            ) {
                $zip->close();
                throw new RuntimeException('The ZIP file contains blocked files or unsafe paths.');
            }

            if ($extractedBytes > self::MAX_PLUGIN_EXTRACTED_BYTES) {
                $zip->close();

                throw new RuntimeException('The extracted plugin package exceeds the 200 MB safety limit.');
            }
        }

        if (! $zip->extractTo($extractPath)) {
            $zip->close();

            throw new RuntimeException('The plugin ZIP could not be extracted.');
        }

        $zip->close();

        $manifestPath = $this->findManifest($extractPath);

        if (! $manifestPath) {
            throw new RuntimeException('The plugin package must contain a valid module.json file.');
        }

        return dirname($manifestPath);
    }

    private function findManifest(string $extractPath): ?string
    {
        $manifests = collect(File::allFiles($extractPath))
            ->filter(fn (\SplFileInfo $file): bool => $file->getFilename() === 'module.json')
            ->values();

        if ($manifests->count() > 1) {
            throw new RuntimeException('The plugin package must contain exactly one module.json file.');
        }

        return $manifests->first()?->getPathname();
    }

    private function assertSamePluginIdentity(Plugin $existing, PluginManifest $manifest): void
    {
        if ($this->normalizeIdentity($existing->name) !== $this->normalizeIdentity($manifest->name)) {
            throw new RuntimeException('The uploaded plugin has the same slug but a different plugin name. Only matching plugin updates are allowed.');
        }

        if ($this->normalizeIdentity($existing->author) !== $this->normalizeIdentity($manifest->author)) {
            throw new RuntimeException('The uploaded plugin has the same slug but a different owner/author. Only same-owner plugin updates are allowed.');
        }
    }

    private function normalizeIdentity(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function storePendingUpdate(
        Request $request,
        PluginUploadWorkspace $uploads,
        string $temporaryZipPath,
        Plugin $existing,
        PluginManifest $manifest,
        string $installedPath,
        string $newPluginRoot,
    ): string {
        $token = bin2hex(random_bytes(20));
        $pendingZipPath = $uploads->preserveForUpdate($temporaryZipPath, $token);

        try {
            $request->session()->put($this->pendingUpdateSessionKey($token), [
                'zip_path' => $pendingZipPath,
                'old' => $this->pluginSummary($existing, $installedPath),
                'new' => $this->manifestSummary($manifest, $newPluginRoot),
                'version_compare' => version_compare($manifest->version, (string) $existing->version),
                'created_at' => now()->toDateTimeString(),
            ]);

            $this->checkpointStep($request, 'plugin.update.review', $manifest->slug, 'pending_update_created', [
                'old_version' => $existing->version,
                'new_version' => $manifest->version,
                'pending_zip_path' => $pendingZipPath,
            ]);
        } catch (\Throwable $exception) {
            $request->session()->forget($this->pendingUpdateSessionKey($token));
            $uploads->discardFile($pendingZipPath);

            throw $exception;
        }

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingUpdate(string $token): ?array
    {
        $pending = session($this->pendingUpdateSessionKey($token));

        return is_array($pending) ? $pending : null;
    }

    private function forgetPendingUpdate(string $token): void
    {
        session()->forget($this->pendingUpdateSessionKey($token));
    }

    private function pendingUpdateSessionKey(string $token): string
    {
        return 'plugin_update.'.$token;
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginSummary(Plugin $plugin, string $installedPath): array
    {
        return [
            'name' => $plugin->name,
            'slug' => $plugin->slug,
            'version' => $plugin->version,
            'author' => $plugin->author,
            'provider' => $plugin->provider,
            'status' => $plugin->status,
            'path' => $installedPath,
            'migrations' => $this->migrationFiles($installedPath),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestSummary(PluginManifest $manifest, string $pluginRoot): array
    {
        return [
            'name' => $manifest->name,
            'slug' => $manifest->slug,
            'version' => $manifest->version,
            'author' => $manifest->author,
            'provider' => $manifest->provider,
            'path' => $pluginRoot,
            'migrations' => $this->migrationFiles($pluginRoot),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function migrationFiles(string $pluginPath): array
    {
        $path = rtrim($pluginPath, '/\\').DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        if (! is_dir($path)) {
            return [];
        }

        return collect(glob($path.DIRECTORY_SEPARATOR.'*.php') ?: [])
            ->map(fn (string $file): string => basename($file))
            ->values()
            ->all();
    }

    private function replaceInstalledPlugin(Request $request, PluginManager $plugins, Plugin $existing, PluginManifest $manifest, string $newPluginRoot, string $installedPath): Plugin
    {
        $wasActive = $existing->status === Plugin::STATUS_ACTIVE;
        $canDeactivateForUpdate = $wasActive && ! $existing->isCore();
        $backupPath = storage_path('app/plugin_updates/backups/'.$manifest->slug.'-'.now()->format('Ymd-His'));
        $newFilesMoved = false;

        File::ensureDirectoryExists(dirname($backupPath));

        if ($canDeactivateForUpdate) {
            $plugins->deactivate($existing);
            $this->checkpointStep($request, 'plugin.update', $manifest->slug, 'old_plugin_deactivated');
        }

        try {
            $this->relocateDirectory($installedPath, $backupPath);
            $this->checkpointStep($request, 'plugin.update', $manifest->slug, 'old_files_moved_to_backup', [
                'backup_path' => $backupPath,
            ]);

            $this->relocateDirectory($newPluginRoot, $installedPath, overwrite: true);
            $newFilesMoved = true;
            $this->checkpointStep($request, 'plugin.update', $manifest->slug, 'new_files_moved_into_modules', [
                'installed_path' => $installedPath,
            ]);

            $plugin = $plugins->update($installedPath);
            $this->checkpointStep($request, 'plugin.update', $manifest->slug, 'plugin_manager_update_completed', [
                'old_version' => $existing->version,
                'new_version' => $plugin->version,
            ]);

            if ($wasActive) {
                $plugin = $plugins->activate($plugin);
                $this->checkpointStep($request, 'plugin.update', $manifest->slug, 'new_plugin_reactivated');
            }

            return $plugin->refresh();
        } catch (\Throwable $exception) {
            if ($newFilesMoved && File::exists($installedPath)) {
                File::deleteDirectory($installedPath);
            }

            $restoreFailure = null;

            if (File::exists($backupPath) && ! File::exists($installedPath)) {
                try {
                    $this->relocateDirectory($backupPath, $installedPath);
                } catch (\Throwable $restoreException) {
                    $restoreFailure = $restoreException;
                }
            }

            if ($wasActive) {
                try {
                    $plugins->activate($existing->fresh() ?: $existing);
                } catch (\Throwable) {
                    //
                }
            }

            if ($restoreFailure !== null) {
                throw new RuntimeException(
                    'Plugin update failed and its backup could not be restored: '.$restoreFailure->getMessage()
                    .'. Original update error: '.$exception->getMessage(),
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    private function relocateDirectory(string $source, string $destination, bool $overwrite = false): void
    {
        if (! File::isDirectory($source)) {
            throw new RuntimeException("Source directory [{$source}] does not exist.");
        }

        if (File::exists($destination)) {
            if (! $overwrite) {
                throw new RuntimeException("Destination directory [{$destination}] already exists.");
            }

            if (! File::deleteDirectory($destination) || File::exists($destination)) {
                throw new RuntimeException("Destination directory [{$destination}] could not be replaced.");
            }
        }

        if (File::moveDirectory($source, $destination)) {
            return;
        }

        // rename() cannot cross Docker bind mounts. Fall back to a verified
        // copy-and-delete relocation when modules and storage are separate.
        if (File::exists($destination)) {
            File::deleteDirectory($destination);
        }

        if (! File::copyDirectory($source, $destination)) {
            File::deleteDirectory($destination);

            throw new RuntimeException("Directory [{$source}] could not be copied to [{$destination}].");
        }

        if (! File::deleteDirectory($source)) {
            File::deleteDirectory($destination);

            throw new RuntimeException("Directory [{$source}] was copied but could not be removed.");
        }
    }

    /**
     * @return array{label:string,route:?string,url:?string,available:bool,note:?string}
     */
    private function settingsLink(Plugin $plugin): array
    {
        $manifest = $this->displayManifest($plugin);
        $settings = data_get($manifest, 'settings', []);
        $label = (string) data_get($settings, 'label', 'Settings');
        $routeName = data_get($settings, 'route');
        $url = data_get($settings, 'url');

        if (! is_string($routeName) || trim($routeName) === '') {
            $routePrefix = data_get($manifest, 'routes.admin.name');
            $routeName = is_string($routePrefix) && trim($routePrefix) !== ''
                ? rtrim($routePrefix, '.').'.index'
                : null;
        }

        if (is_string($routeName) && Route::has($routeName)) {
            return [
                'label' => $label,
                'route' => $routeName,
                'url' => route($routeName),
                'available' => true,
                'note' => null,
            ];
        }

        $fallbackUrl = $this->settingsFallbackUrl($plugin, $manifest);

        if ($plugin->status === Plugin::STATUS_ACTIVE && $fallbackUrl !== null) {
            return [
                'label' => $label,
                'route' => is_string($routeName) ? $routeName : null,
                'url' => $fallbackUrl,
                'available' => true,
                'note' => null,
            ];
        }

        if (is_string($url) && trim($url) !== '') {
            return [
                'label' => $label,
                'route' => null,
                'url' => url($url),
                'available' => true,
                'note' => null,
            ];
        }

        return [
            'label' => $label,
            'route' => is_string($routeName) ? $routeName : null,
            'url' => null,
            'available' => false,
            'note' => $plugin->status === Plugin::STATUS_ACTIVE
                ? 'Settings route is not registered.'
                : 'Activate the plugin to register its settings route.',
        ];
    }

    private function settingsFallbackUrl(Plugin $plugin, array $manifest): ?string
    {
        $prefix = data_get($manifest, 'routes.admin.prefix');

        if (! is_string($prefix) || trim($prefix) === '') {
            $prefix = 'admin/plugins/'.$plugin->slug;
        }

        $prefix = trim($prefix, '/');

        if ($prefix === '' || str_contains($prefix, '..') || str_contains($prefix, "\0")) {
            return null;
        }

        return url($prefix);
    }

    /**
     * @return array<string, mixed>
     */
    private function displayManifest(Plugin $plugin): array
    {
        $path = $plugin->path
            ?: $plugin->getAttribute('installed_path')
            ?: $plugin->getAttribute('source_path')
            ?: base_path('modules/'.$plugin->slug);

        if (is_string($path) && trim($path) !== '') {
            $manifestPath = base_path(ltrim($path, '/\\')).'/module.json';

            if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)) {
                $manifestPath = rtrim($path, '/\\').'/module.json';
            }

            if (File::exists($manifestPath)) {
                $decoded = json_decode((string) File::get($manifestPath), true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return is_array($plugin->manifest) ? $plugin->manifest : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function checkpointStep(Request $request, string $operationType, ?string $targetSlug, string $step, array $metadata = []): void
    {
        app(StepBackupper::class)->afterStep(
            $operationType,
            'plugin',
            $targetSlug,
            $step,
            $metadata,
            $request->user()?->id,
        );
    }
}
