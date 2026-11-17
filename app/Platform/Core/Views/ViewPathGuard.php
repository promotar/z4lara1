<?php

namespace App\Platform\Core\Views;

use RuntimeException;

class ViewPathGuard
{
    public function viewToRelativePath(string $view): string
    {
        $view = trim($view);

        if ($view === '' || str_contains($view, '..') || str_contains($view, "\0")) {
            throw new RuntimeException("Unsafe view name [{$view}].");
        }

        $view = str_replace(['/', '\\'], '.', $view);

        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $view)) {
            throw new RuntimeException("Unsafe view name [{$view}].");
        }

        return str_replace('.', DIRECTORY_SEPARATOR, $view).'.blade.php';
    }

    public function pathInside(string $root, string $relativePath): ?string
    {
        $rootPath = realpath($root);

        if ($rootPath === false || ! is_dir($rootPath)) {
            return null;
        }

        $candidate = $rootPath.DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved)) {
            return null;
        }

        $rootPrefix = rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($resolved, $rootPrefix)) {
            throw new RuntimeException("Resolved view path escapes root [{$root}].");
        }

        return $resolved;
    }

    public function directoryInside(string $root, string $relativePath = ''): ?string
    {
        $rootPath = realpath($root);

        if ($rootPath === false || ! is_dir($rootPath)) {
            return null;
        }

        $candidate = $relativePath === ''
            ? $rootPath
            : $rootPath.DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');

        $resolved = realpath($candidate);

        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        $rootPrefix = rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($resolved !== $rootPath && ! str_starts_with($resolved, $rootPrefix)) {
            throw new RuntimeException("Resolved view directory escapes root [{$root}].");
        }

        return $resolved;
    }
}
