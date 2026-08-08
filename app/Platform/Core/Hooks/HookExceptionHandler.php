<?php

namespace App\Platform\Core\Hooks;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\Log;
use Throwable;

class HookExceptionHandler
{
    public function loadingFailed(Plugin $plugin, string $path, Throwable $exception): void
    {
        Log::warning('Plugin hook file loading failed.', [
            'plugin' => $plugin->slug,
            'path' => $path,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    public function callbackFailed(string $type, string $hook, HookCallback $callback, Throwable $exception): void
    {
        Log::warning('Hook callback execution failed.', [
            'type' => $type,
            'hook' => $hook,
            'callback' => $callback->id,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
