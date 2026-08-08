<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Http\Middleware\EnsurePluginIsActive;
use App\Platform\Core\Logs\PlatformLogManager;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Registry\PlatformRegistry;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use ParseError;
use Throwable;

class PluginRouteLoader
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PlatformRegistry $registry,
        private readonly PlatformLogManager $platformLogs,
        private readonly PluginRuntimeGate $gate,
        private readonly PluginFilesystem $filesystem,
        private readonly RequiredCorePluginSynchronizer $requiredCorePlugins,
    ) {
        //
    }

    public function loadWebRoutes(): void
    {
        $this->loadRoutes('web');
    }

    public function loadAdminRoutes(): void
    {
        $this->loadRoutes('admin');
    }

    public function loadApiRoutes(): void
    {
        $this->loadRoutes('api');
    }

    private function loadRoutes(string $type): void
    {
        foreach ($this->activePlugins() as $plugin) {
            $this->loadRoutesForPlugin($plugin, $type);
        }
    }

    /**
     * @return iterable<int, Plugin>
     */
    private function activePlugins(): iterable
    {
        try {
            if (! Schema::hasTable('plugins')) {
                return [];
            }

            $this->requiredCorePlugins->synchronize();

            return $this->plugins->findActive();
        } catch (Throwable $exception) {
            Log::warning('Plugin route loading skipped because active plugins could not be loaded.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function loadRoutesForPlugin(Plugin $plugin, string $type): void
    {
        if (! $this->gate->allows($plugin->slug)) {
            return;
        }

        $pluginPath = $this->filesystem->path($plugin);

        if ($pluginPath === null) {
            Log::warning('Plugin route loading skipped because plugin path could not be resolved.', [
                'plugin' => $plugin->slug,
                'route_type' => $type,
            ]);

            return;
        }

        $routeFile = $this->routeFile($plugin, $type);

        if ($routeFile === null) {
            return;
        }

        if (! $this->routeFileHasValidSyntax($plugin, $routeFile, $type)) {
            return;
        }

        if (! $this->registry->pluginRouteFileIsRegistered($plugin->slug, $type)) {
            $this->platformLogs->error('Blocked unregistered plugin route file.', [
                'plugin' => $plugin->slug,
                'route_type' => $type,
                'route_file' => $routeFile,
            ]);

            return;
        }

        try {
            Route::middleware($this->middleware($plugin, $type))
                ->prefix($this->prefix($plugin, $type))
                ->name($this->namePrefix($plugin, $type))
                ->group(function () use ($routeFile): void {
                    require $routeFile;
                });

            $this->platformLogs->success('Loaded registered plugin route file.', [
                'plugin' => $plugin->slug,
                'route_type' => $type,
                'route_file' => $routeFile,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Plugin route loading failed while loading route file.', [
                'plugin' => $plugin->slug,
                'route_type' => $type,
                'route_file' => $routeFile,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function routeFileHasValidSyntax(Plugin $plugin, string $routeFile, string $type): bool
    {
        $source = file_get_contents($routeFile);

        if ($source === false) {
            Log::warning('Plugin route loading skipped because route file could not be read.', [
                'plugin' => $plugin->slug,
                'route_type' => $type,
                'route_file' => $routeFile,
            ]);

            return false;
        }

        try {
            token_get_all($source, TOKEN_PARSE);

            return true;
        } catch (ParseError $exception) {
            Log::warning('Plugin route loading skipped because route file has invalid PHP syntax.', [
                'plugin' => $plugin->slug,
                'route_type' => $type,
                'route_file' => $routeFile,
                'syntax_check' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function routeFile(Plugin $plugin, string $type): ?string
    {
        $configured = data_get($this->filesystem->manifest($plugin), "routes.{$type}.file");

        if (is_string($configured) && trim($configured) !== '') {
            return $this->filesystem->file($plugin, $configured);
        }

        return $this->filesystem->file($plugin, 'routes'.DIRECTORY_SEPARATOR.$type.'.php');
    }

    /**
     * @return array<int, string>
     */
    private function middleware(Plugin $plugin, string $type): array
    {
        $configured = data_get($this->filesystem->manifest($plugin), "routes.{$type}.middleware");

        if (is_array($configured)) {
            return $this->withRuntimeGate($plugin, array_values(array_filter($configured, 'is_string')));
        }

        if (is_string($configured) && trim($configured) !== '') {
            return $this->withRuntimeGate($plugin, [$configured]);
        }

        return $this->withRuntimeGate($plugin, match ($type) {
            'admin' => ['web', 'auth', 'staff'],
            'api' => ['api'],
            default => ['web'],
        });
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function withRuntimeGate(Plugin $plugin, array $middleware): array
    {
        array_unshift($middleware, EnsurePluginIsActive::class.':'.$plugin->slug);

        return $middleware;
    }

    private function prefix(Plugin $plugin, string $type): string
    {
        $configured = data_get($this->filesystem->manifest($plugin), "routes.{$type}.prefix");

        if (is_string($configured)) {
            return trim($configured, '/');
        }

        return match ($type) {
            'admin' => 'admin/plugins/'.$plugin->slug,
            'api' => 'plugins/'.$plugin->slug,
            default => '',
        };
    }

    private function namePrefix(Plugin $plugin, string $type): string
    {
        $configured = data_get($this->filesystem->manifest($plugin), "routes.{$type}.name");

        if (is_string($configured)) {
            return $this->normalizeNamePrefix($configured);
        }

        return match ($type) {
            'admin' => "admin.plugins.{$plugin->slug}.",
            'api' => "api.plugins.{$plugin->slug}.",
            default => '',
        };
    }

    private function normalizeNamePrefix(string $prefix): string
    {
        $prefix = trim($prefix, '.');

        return $prefix === '' ? '' : $prefix.'.';
    }
}
