<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use RuntimeException;

class PluginOwnershipValidator
{
    public function validate(string $pluginPath, PluginManifest $manifest): void
    {
        if (data_get($manifest->manifest, 'uninstall.script') !== null) {
            throw new RuntimeException(
                "Plugin [{$manifest->slug}] cannot execute uninstall.php; declare all owned resources in uninstall."
            );
        }

        $declaredTables = $this->declaredTables($manifest);
        $migrationTables = $this->migrationTables($pluginPath, $manifest);
        $missingTables = array_values(array_diff($migrationTables, $declaredTables));

        if ($missingTables !== []) {
            throw new RuntimeException(
                "Plugin [{$manifest->slug}] must declare every migration-owned table in "
                .'uninstall.tables. Missing: '.implode(', ', $missingTables)
            );
        }

        $undeclaredMigrations = array_values(array_diff($declaredTables, $migrationTables));

        if ($undeclaredMigrations !== []) {
            throw new RuntimeException(
                "Plugin [{$manifest->slug}] declares owned tables without matching Schema::create migrations: "
                .implode(', ', $undeclaredMigrations)
            );
        }

        foreach ((array) data_get($manifest->manifest, 'uninstall.settings', []) as $setting) {
            if (! is_string($setting) || preg_match('/^[A-Za-z0-9_.:-]+$/', $setting) !== 1) {
                throw new RuntimeException("Plugin [{$manifest->slug}] declares an unsafe uninstall setting key.");
            }
        }

        foreach ((array) data_get($manifest->manifest, 'uninstall.storage_paths', []) as $storagePath) {
            if (! is_array($storagePath)) {
                throw new RuntimeException("Plugin [{$manifest->slug}] declares an invalid uninstall storage path.");
            }

            $disk = $storagePath['disk'] ?? null;
            $path = $storagePath['path'] ?? null;

            if (
                ! is_string($disk)
                || ! in_array($disk, ['local', 'public'], true)
                || ! is_string($path)
                || ! $this->safeRelativePath($path)
            ) {
                throw new RuntimeException("Plugin [{$manifest->slug}] declares an unsafe uninstall storage path.");
            }
        }

        $modifiedTables = $this->migrationSchemaTables($pluginPath, $manifest, 'table');

        foreach ((array) data_get($manifest->manifest, 'uninstall.columns', []) as $definition) {
            $table = (string) data_get($definition, 'table');
            $columns = (array) data_get($definition, 'columns', []);

            if (
                ! in_array($table, $modifiedTables, true)
                || in_array($table, $declaredTables, true)
                || $columns === []
            ) {
                throw new RuntimeException(
                    "Plugin [{$manifest->slug}] declares an invalid shared-column ownership rule."
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function declaredTables(PluginManifest $manifest): array
    {
        $tables = (array) data_get($manifest->manifest, 'uninstall.tables', []);

        foreach ($tables as $table) {
            if (! is_string($table) || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                throw new RuntimeException("Plugin [{$manifest->slug}] declares an unsafe uninstall table.");
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return array<int, string>
     */
    private function migrationTables(string $pluginPath, PluginManifest $manifest): array
    {
        $configured = data_get($manifest->manifest, 'install.migrations')
            ?: data_get($manifest->manifest, 'migrations')
            ?: 'database/migrations';
        $path = rtrim($pluginPath, '/\\').DIRECTORY_SEPARATOR.ltrim((string) $configured, '/\\');

        if (! is_dir($path)) {
            return [];
        }

        return $this->migrationSchemaTables($pluginPath, $manifest, 'create');
    }

    /**
     * @return array<int, string>
     */
    private function migrationSchemaTables(
        string $pluginPath,
        PluginManifest $manifest,
        string $method,
    ): array {
        $configured = data_get($manifest->manifest, 'install.migrations')
            ?: data_get($manifest->manifest, 'migrations')
            ?: 'database/migrations';
        $path = rtrim($pluginPath, '/\\').DIRECTORY_SEPARATOR.ltrim((string) $configured, '/\\');

        if (! is_dir($path)) {
            return [];
        }

        $tables = [];

        foreach (glob($path.DIRECTORY_SEPARATOR.'*.php') ?: [] as $migration) {
            $tables = array_merge($tables, $this->schemaTablesInFile($migration, $method));
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return array<int, string>
     */
    private function schemaTablesInFile(string $path, string $method): array
    {
        $tokens = token_get_all((string) file_get_contents($path));
        $tables = [];

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (! $this->tokenEquals($tokens[$index], T_STRING, 'Schema')) {
                continue;
            }

            $doubleColon = $this->nextMeaningfulToken($tokens, $index + 1);
            $create = $doubleColon === null ? null : $this->nextMeaningfulToken($tokens, $doubleColon + 1);
            $open = $create === null ? null : $this->nextMeaningfulToken($tokens, $create + 1);
            $table = $open === null ? null : $this->nextMeaningfulToken($tokens, $open + 1);

            if (
                $doubleColon === null
                || ! $this->tokenEquals($tokens[$doubleColon], T_DOUBLE_COLON)
                || $create === null
                || ! $this->tokenEquals($tokens[$create], T_STRING, $method)
                || $open === null
                || $tokens[$open] !== '('
                || $table === null
                || ! is_array($tokens[$table])
                || $tokens[$table][0] !== T_CONSTANT_ENCAPSED_STRING
            ) {
                continue;
            }

            $tables[] = stripcslashes(substr($tokens[$table][1], 1, -1));
        }

        return $tables;
    }

    /**
     * @param  array<int, array{int, string, int}|string>  $tokens
     */
    private function nextMeaningfulToken(array $tokens, int $offset): ?int
    {
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (
                is_array($token)
                && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function tokenEquals(mixed $token, int $type, ?string $value = null): bool
    {
        return is_array($token)
            && $token[0] === $type
            && ($value === null || strcasecmp($token[1], $value) === 0);
    }

    private function safeRelativePath(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path !== ''
            && ! str_contains($path, '..')
            && preg_match('/^[A-Za-z0-9_.\/-]+$/', $path) === 1;
    }
}
