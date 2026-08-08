<?php

namespace App\Platform\Core\Hooks;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\PluginRuntimeRegistry;
use App\Platform\Core\Services\PluginFilesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PluginHookLoader
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginRuntimeRegistry $runtime,
        private readonly \App\Platform\Core\Services\PluginRuntimeGate $gate,
        private readonly HookExceptionHandler $exceptions,
        private readonly PluginFilesystem $filesystem,
    ) {
        //
    }

    public function loadActiveHooks(HookManager $hooks): int
    {
        try {
            if (! Schema::hasTable('plugins')) {
                return 0;
            }

            $plugins = $this->plugins->findActive();
        } catch (Throwable $exception) {
            Log::warning('Plugin hook loading skipped because active plugins could not be loaded.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }

        $loaded = 0;

        foreach ($plugins as $plugin) {
            if (! $this->gate->allows($plugin->slug)) {
                continue;
            }

            if (! $this->runtime->hooksEnabled($plugin->slug)) {
                continue;
            }

            if ($this->loadPluginHooks($plugin, $hooks)) {
                $loaded++;
            }
        }

        return $loaded;
    }

    private function loadPluginHooks(Plugin $plugin, HookManager $hooks): bool
    {
        $path = $this->hookFilePath($plugin);

        if ($path === null || ! is_file($path)) {
            return false;
        }

        try {
            $registrar = require $path;

            if (! is_callable($registrar)) {
                Log::warning('Plugin hook file skipped because it did not return a callable registrar.', [
                    'plugin' => $plugin->slug,
                    'path' => $path,
                ]);

                return false;
            }

            $registrar($hooks);

            return true;
        } catch (Throwable $exception) {
            $this->exceptions->loadingFailed($plugin, $path, $exception);

            return false;
        }
    }

    private function hookFilePath(Plugin $plugin): ?string
    {
        return $this->filesystem->file($plugin, 'hooks.php');
    }
}
