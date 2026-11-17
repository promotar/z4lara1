<?php

namespace App\Platform\Core\Logs;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use SplFileObject;
use Throwable;

class LiveLogReader
{
    /**
     * @return array<string, mixed>
     */
    public function latest(int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));
        $sources = $this->sources();
        $entries = [];

        foreach ($sources as $source) {
            foreach ($this->tailLines($source['path'], min(250, $limit)) as $lineNumber => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $entries[] = $this->entry($source, $line, (int) $lineNumber);
            }
        }

        usort($entries, fn (array $left, array $right): int => [
            $left['file_mtime'],
            $left['line_number'],
            $left['source'],
        ] <=> [
            $right['file_mtime'],
            $right['line_number'],
            $right['source'],
        ]);

        $entries = array_slice($entries, -$limit);

        return [
            'generated_at' => now()->toDateTimeString(),
            'count' => count($entries),
            'sources' => array_values(array_map(fn (array $source): array => [
                'source' => $source['source'],
                'file' => $source['file'],
                'path' => $source['relative_path'],
                'mtime' => $source['mtime'],
            ], $sources)),
            'logs' => array_values(array_map(fn (array $entry): array => [
                'source' => $entry['source'],
                'file' => $entry['file'],
                'path' => $entry['path'],
                'timestamp' => $entry['timestamp'],
                'level' => $entry['level'],
                'message' => $entry['message'],
                'line' => $entry['line'],
            ], $entries)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sources(): array
    {
        $sources = [];
        $this->addLogFiles($sources, 'core', storage_path('logs'), storage_path('logs'));

        foreach ($this->activePlugins() as $plugin) {
            $pluginPath = $this->pluginPath($plugin);

            if ($pluginPath === null) {
                continue;
            }

            $this->addLogFiles($sources, 'plugin:'.$plugin->slug, $pluginPath.DIRECTORY_SEPARATOR.'logs', $pluginPath);
            $this->addLogFiles($sources, 'plugin:'.$plugin->slug, $pluginPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs', $pluginPath);
        }

        usort($sources, fn (array $left, array $right): int => [
            $left['mtime'],
            $left['source'],
            $left['file'],
        ] <=> [
            $right['mtime'],
            $right['source'],
            $right['file'],
        ]);

        return $sources;
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     */
    private function addLogFiles(array &$sources, string $source, string $directory, string $allowedRoot): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            $path = $file->getRealPath();

            if (! is_string($path) || $file->getExtension() !== 'log' || ! $this->isSafeLogPath($path, $allowedRoot)) {
                continue;
            }

            $sources[] = [
                'source' => $source,
                'file' => $file->getFilename(),
                'path' => $path,
                'relative_path' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
                'mtime' => $file->getMTime(),
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function tailLines(string $path, int $lines): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        try {
            $file = new SplFileObject($path, 'r');
            $file->seek(PHP_INT_MAX);
            $lastLine = $file->key();
            $start = max(0, $lastLine - $lines + 1);
            $result = [];

            $file->seek($start);

            while (! $file->eof()) {
                $line = rtrim((string) $file->current(), "\r\n");

                if ($line !== '') {
                    $result[$file->key()] = $line;
                }

                $file->next();
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, Plugin>
     */
    private function activePlugins(): array
    {
        try {
            if (! Schema::hasTable('plugins')) {
                return [];
            }

            return Plugin::query()
                ->active()
                ->get(['slug', 'path'])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function pluginPath(Plugin $plugin): ?string
    {
        $path = is_string($plugin->path) && $plugin->path !== ''
            ? $plugin->path
            : base_path('modules'.DIRECTORY_SEPARATOR.$plugin->slug);

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        $resolved = realpath($path);

        return is_string($resolved) && str_starts_with($resolved, base_path('modules').DIRECTORY_SEPARATOR)
            ? $resolved
            : null;
    }

    private function isSafeLogPath(string $path, string $allowedRoot): bool
    {
        $root = realpath($allowedRoot);
        $resolved = realpath($path);

        return is_string($root)
            && is_string($resolved)
            && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function entry(array $source, string $line, int $lineNumber): array
    {
        $timestamp = null;
        $level = null;
        $message = $line;

        if (preg_match('/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(?<channel>[^.]+)\.(?<level>[A-Z]+):\s*(?<message>.*)$/', $line, $matches)) {
            $timestamp = $matches['timestamp'];
            $level = strtolower($matches['level']);
            $message = $matches['message'];
        } elseif (preg_match('/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(?<level>[A-Z]+):\s*(?<message>.*)$/', $line, $matches)) {
            $timestamp = $matches['timestamp'];
            $level = strtolower($matches['level']);
            $message = $matches['message'];
        } elseif (preg_match('/\b(ERROR|WARNING|WARN|INFO|DEBUG|CRITICAL|ALERT|EMERGENCY)\b/i', $line, $matches)) {
            $level = strtolower($matches[1]);
        }

        return [
            'source' => $source['source'],
            'file' => $source['file'],
            'path' => $source['relative_path'],
            'file_mtime' => $source['mtime'],
            'line_number' => $lineNumber,
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'line' => $line,
        ];
    }
}
