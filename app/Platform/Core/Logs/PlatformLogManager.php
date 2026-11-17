<?php

namespace App\Platform\Core\Logs;

use Illuminate\Support\Facades\Log;

class PlatformLogManager
{
    public function success(string $message, array $context = []): void
    {
        $this->write(storage_path('logs/platform-success.log'), 'SUCCESS', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(storage_path('logs/platform-error.log'), 'ERROR', $message, $context);
    }

    /**
     * @return array<int, string>
     */
    public function recentSuccess(int $lines = 80): array
    {
        return $this->tail(storage_path('logs/platform-success.log'), $lines);
    }

    /**
     * @return array<int, string>
     */
    public function recentErrors(int $lines = 80): array
    {
        return $this->tail(storage_path('logs/platform-error.log'), $lines);
    }

    private function write(string $path, string $level, string $message, array $context): void
    {
        $entry = sprintf('[%s] %s: %s %s', now()->toDateTimeString(), $level, $message, $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES));

        try {
            file_put_contents($path, rtrim($entry).PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
            Log::warning('Platform log write failed.', [
                'path' => $path,
                'level' => $level,
                'message' => $message,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function tail(string $path, int $lines): array
    {
        if (! is_file($path)) {
            return [];
        }

        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($content)) {
            return [];
        }

        return array_slice($content, -max(1, $lines));
    }
}
