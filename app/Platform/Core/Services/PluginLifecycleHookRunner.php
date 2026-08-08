<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

/**
 * Executes explicitly declared plugin lifecycle callbacks without knowing any
 * plugin-specific class. Missing declarations are valid no-ops.
 */
class PluginLifecycleHookRunner
{
    public function __construct(private readonly Container $container)
    {
    }

    public function run(PluginManifest $manifest, string $hook): void
    {
        $callback = data_get($manifest->manifest, "lifecycle.{$hook}");

        if ($callback === null || $callback === '') {
            return;
        }

        [$class, $method] = $this->parseCallback($callback, $hook);
        $this->loadDeclaredFile($manifest);

        if (! class_exists($class)) {
            throw new RuntimeException("Plugin lifecycle class [{$class}] does not exist.");
        }

        $instance = $this->container->make($class);

        if (! is_callable([$instance, $method])) {
            throw new RuntimeException("Plugin lifecycle callback [{$class}@{$method}] is not callable.");
        }

        $instance->{$method}();
    }

    /** @return array{0: class-string, 1: string} */
    private function parseCallback(mixed $callback, string $hook): array
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = array_map('trim', explode('@', $callback, 2));
        } elseif (is_array($callback)) {
            $class = trim((string) ($callback['class'] ?? ''));
            $method = trim((string) ($callback['method'] ?? $hook));
        } else {
            throw new InvalidArgumentException("Invalid plugin lifecycle declaration for [{$hook}].");
        }

        if ($class === '' || $method === '') {
            throw new InvalidArgumentException("Incomplete plugin lifecycle declaration for [{$hook}].");
        }

        return [$class, $method];
    }

    private function loadDeclaredFile(PluginManifest $manifest): void
    {
        $configured = data_get($manifest->manifest, 'lifecycle.file');

        if (! is_string($configured) || trim($configured) === '') {
            return;
        }

        $source = $manifest->sourcePath;
        $resolvedSource = is_string($source) ? realpath($source) : false;
        $root = $resolvedSource !== false && is_file($resolvedSource)
            ? dirname($resolvedSource)
            : $resolvedSource;

        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('Plugin lifecycle source path is unavailable.');
        }

        $file = realpath($root.DIRECTORY_SEPARATOR.ltrim(trim($configured), '/\\'));
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($file === false || ! is_file($file) || ! str_starts_with($file, $rootPrefix)) {
            throw new RuntimeException('Plugin lifecycle file is missing or escapes the plugin directory.');
        }

        require_once $file;
    }
}
