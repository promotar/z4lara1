<?php

namespace App\Platform\Core\Updates;

use Illuminate\Support\Facades\File;
use Throwable;

class FailedUpdateHandler
{
    /**
     * @param array<string, mixed> $checkpoint
     */
    public function handle(Throwable $exception, UpdateResult $result, array $checkpoint, callable $restore): UpdateResult
    {
        $restoreError = null;

        try {
            $restore();
        } catch (Throwable $restoreException) {
            $restoreError = $restoreException->getMessage();
        }

        $logPath = $this->writeLog([
            'result' => $result->toArray(),
            'checkpoint' => $checkpoint,
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ],
            'restore_error' => $restoreError,
            'failed_at' => now()->toIso8601String(),
        ]);

        return $result->withLogPath($logPath)->withMetadata([
            'exception' => $exception->getMessage(),
            'restore_error' => $restoreError,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeLog(array $payload): string
    {
        $directory = storage_path('app/platform/update-logs');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.now()->format('YmdHis').'-'.$payload['result']['type'].'-'.$payload['result']['slug'].'-failed.json';
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
