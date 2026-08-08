<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class PluginServiceProviderLoader
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginRuntimeGate $gate,
        private readonly PluginFilesystem $filesystem,
        private readonly RequiredCorePluginSynchronizer $requiredCorePlugins,
    ) {}

    public function registerActiveProviders(Application $app): void
    {
        try {
            if (! Schema::hasTable('plugins')) {
                return;
            }

            $this->requiredCorePlugins->synchronize();
            $plugins = $this->plugins->findActive();
        } catch (Throwable $exception) {
            Log::warning('Plugin service provider loading skipped because active plugins could not be loaded.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($plugins as $plugin) {
            $this->registerProviderForPlugin($app, $plugin);
        }
    }

    private function registerProviderForPlugin(Application $app, Plugin $plugin): void
    {
        if (! $this->gate->allows($plugin->slug)) {
            return;
        }

        $provider = $this->providerClass($plugin);

        if ($provider === null) {
            Log::warning('Plugin service provider loading skipped because no provider class was defined.', ['plugin' => $plugin->slug]);

            return;
        }

        $this->registerPluginNamespace($plugin, $provider);
        $this->loadProviderFile($plugin, $provider);

        if (! class_exists($provider)) {
            Log::warning('Plugin service provider loading skipped because provider class does not exist.', ['plugin' => $plugin->slug, 'provider' => $provider]);

            return;
        }

        if (! is_subclass_of($provider, ServiceProvider::class)) {
            Log::warning('Plugin service provider loading skipped because provider class is invalid.', ['plugin' => $plugin->slug, 'provider' => $provider]);

            return;
        }

        if (($app->getLoadedProviders()[$provider] ?? false) === true) {
            return;
        }

        try {
            $app->register($provider);
        } catch (Throwable $exception) {
            Log::warning('Plugin service provider loading failed while registering provider.', [
                'plugin' => $plugin->slug,
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function providerClass(Plugin $plugin): ?string
    {
        $provider = $plugin->provider ?: data_get($plugin->manifest, 'provider');

        if (! is_string($provider)) {
            return null;
        }

        $provider = trim($provider);

        while (str_contains($provider, '\\\\')) {
            $provider = str_replace('\\\\', '\\', $provider);
        }

        return $provider !== '' ? $provider : null;
    }

    private function loadProviderFile(Plugin $plugin, string $provider): void
    {
        if (class_exists($provider, false)) {
            return;
        }

        $pluginPath = $this->filesystem->path($plugin);

        if ($pluginPath === null) {
            return;
        }

        $basename = class_basename($provider).'.php';
        $configured = data_get($plugin->manifest, 'provider_file');
        $candidates = [];

        if (is_string($configured) && trim($configured) !== '') {
            $candidates[] = $pluginPath.DIRECTORY_SEPARATOR.ltrim(trim($configured), '/\\');
        }

        $candidates[] = $pluginPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.$basename;
        $candidates[] = $pluginPath.DIRECTORY_SEPARATOR.$basename;
        $candidates[] = $pluginPath.DIRECTORY_SEPARATOR.'ServiceProvider.php';

        foreach (array_unique($candidates) as $candidate) {
            $relative = ltrim(str_replace($pluginPath, '', $candidate), '/\\');
            $providerFile = $this->filesystem->file($plugin, $relative);

            if ($providerFile !== null) {
                require_once $providerFile;

                return;
            }
        }
    }

    private function registerPluginNamespace(Plugin $plugin, string $provider): void
    {
        $pluginPath = $this->filesystem->path($plugin);
        $separator = strrpos($provider, '\\');

        if ($pluginPath === null || $separator === false) {
            return;
        }

        $namespace = substr($provider, 0, $separator + 1);
        $sourcePath = is_dir($pluginPath.DIRECTORY_SEPARATOR.'src')
            ? $pluginPath.DIRECTORY_SEPARATOR.'src'
            : $pluginPath;

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4($namespace, $sourcePath, true);
        }
    }
}
