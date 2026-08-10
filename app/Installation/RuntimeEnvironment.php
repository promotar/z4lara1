<?php

namespace App\Installation;

final class RuntimeEnvironment
{
    public static function path(): string
    {
        return dirname(__DIR__, 2).'/storage/app/platform/installation.env';
    }

    public static function completionPath(): string
    {
        return dirname(self::path()).'/installation.complete';
    }

    public static function load(): void
    {
        self::ensureRuntimeDirectories();

        $path = self::path();
        if (is_file($path) && is_readable($path)) {
            self::loadFile($path);
        }

        self::ensureInstallerApplicationKey();
    }

    private static function loadFile(string $path): void
    {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $processValue = getenv($key);
            if (is_string($processValue) && trim($processValue) !== '') {
                continue;
            }

            $value = trim(trim($value), "\"'");
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private static function ensureInstallerApplicationKey(): void
    {
        if (self::value('APP_KEY') !== '' || self::installedFlag() === '1') {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        putenv('APP_KEY='.$key);
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;

        self::writeValue(self::path(), 'APP_KEY', $key, true);

        $environmentPath = dirname(__DIR__, 2).'/.env';
        if (is_file($environmentPath) && is_writable($environmentPath)) {
            self::writeValue($environmentPath, 'APP_KEY', $key);
        }
    }

    private static function installedFlag(): string
    {
        if (is_file(self::completionPath())) {
            return '1';
        }

        foreach (['INSTALLATION_COMPLETE', 'INSTAAL_IS_ACTIVE', 'INSTAAL_IS_ATIVE'] as $key) {
            if (self::value($key, '0') === '1') {
                return '1';
            }
        }

        return '0';
    }

    private static function value(string $key, string $default = ''): string
    {
        $processValue = getenv($key);
        if (is_string($processValue) && trim($processValue) !== '') {
            return trim(trim($processValue), "\"'");
        }

        foreach ([self::path(), dirname(__DIR__, 2).'/.env', dirname(__DIR__, 2).'/.env.installation'] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $content = file_get_contents($path);
            if (is_string($content) && preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $match) === 1) {
                return trim(trim($match[1]), "\"'");
            }
        }

        return $default;
    }

    private static function writeValue(string $path, string $key, string $value, bool $protect = false): void
    {
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        $line = $key.'="'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $content = preg_match($pattern, $content)
            ? (string) preg_replace($pattern, $line, $content)
            : rtrim($content).PHP_EOL.$line.PHP_EOL;

        file_put_contents($path, ltrim($content), LOCK_EX);
        if ($protect) {
            @chmod($path, 0660);
        }
    }

    private static function ensureRuntimeDirectories(): void
    {
        $basePath = dirname(__DIR__, 2);
        $directories = [
            $basePath.'/bootstrap/cache',
            $basePath.'/storage/app/platform',
            $basePath.'/storage/framework/cache/data',
            $basePath.'/storage/framework/sessions',
            $basePath.'/storage/framework/views',
            $basePath.'/storage/logs',
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }
    }
}
