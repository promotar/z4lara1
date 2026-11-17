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

        // Read only the end of the file: platform logs can grow to gigabytes and
        // file()/file_get_contents() would exhaust the PHP memory limit.
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            $window = 512 * 1024;
            $stats = fstat($handle);
            $size = (int) ($stats['size'] ?? 0);
            $offset = max(0, $size - $window);

            fseek($handle, $offset);
            $chunk = (string) fread($handle, $window);
        } finally {
            fclose($handle);
        }

        if ($offset > 0) {
            $firstBreak = strpos($chunk, "\n");

            if ($firstBreak === false) {
                return [];
            }

            $chunk = substr($chunk, $firstBreak + 1);
        }

        $content = preg_split('/\r\n|\r|\n/', $chunk, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $content = array_map(fn (string $line): string => mb_substr($line, 0, 4000), $content);

        return array_slice($content, -max(1, min($lines, 500)));
    }
}
