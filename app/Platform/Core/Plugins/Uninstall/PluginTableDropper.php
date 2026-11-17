<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PluginTableDropper
{
    /**
     * @return array<int, string>
     */
    public function drop(PluginManifest $manifest): array
    {
        $dropped = [];
        $tables = $this->declaredTables($manifest);

        foreach ($tables as $table) {
            $this->guardTableName($table);
        }

        foreach ($this->dependencyOrderedTables($manifest, $tables) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Log::info('Dropping plugin-owned table declared for uninstall.', [
                'plugin' => $manifest->slug,
                'table' => $table,
            ]);

            Schema::dropIfExists($table);
            $dropped[] = $table;
        }

        return $dropped;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function dependencyOrderedTables(PluginManifest $manifest, array $tables): array
    {
        $owned = array_fill_keys($tables, true);
        $edges = array_fill_keys($tables, []);
        $incoming = array_fill_keys($tables, 0);
        $externalReferences = [];

        foreach ($this->databaseTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $referenced = $this->unqualifiedTableName((string) ($foreignKey['foreign_table'] ?? ''));

                if ($referenced === '' || ! isset($owned[$referenced]) || $table === $referenced) {
                    continue;
                }

                if (! isset($owned[$table])) {
                    $externalReferences[] = $table.'.'.(string) ($foreignKey['name'] ?? 'foreign_key');

                    continue;
                }

                if (! in_array($referenced, $edges[$table], true)) {
                    $edges[$table][] = $referenced;
                    $incoming[$referenced]++;
                }
            }
        }

        if ($externalReferences !== []) {
            throw new RuntimeException(
                "Cannot purge plugin [{$manifest->slug}]; tables outside its ownership reference "
                .'plugin tables: '.implode(', ', array_unique($externalReferences))
            );
        }

        $ready = array_values(array_filter(
            $tables,
            fn (string $table): bool => $incoming[$table] === 0,
        ));
        $ordered = [];

        while ($ready !== []) {
            $table = array_shift($ready);
            $ordered[] = $table;

            foreach ($edges[$table] as $referenced) {
                $incoming[$referenced]--;

                if ($incoming[$referenced] === 0) {
                    $ready[] = $referenced;
                }
            }
        }

        if (count($ordered) !== count($tables)) {
            $cyclic = array_values(array_diff($tables, $ordered));

            throw new RuntimeException(
                "Cannot purge plugin [{$manifest->slug}]; cyclic foreign keys exist between owned tables: "
                .implode(', ', $cyclic)
            );
        }

        return $ordered;
    }

    /**
     * @return array<int, string>
     */
    private function databaseTables(): array
    {
        return array_values(array_unique(array_map(
            fn (string $table): string => $this->unqualifiedTableName($table),
            Schema::getTableListing(),
        )));
    }

    private function unqualifiedTableName(string $table): string
    {
        $segments = explode('.', trim($table, '`"'));

        return trim((string) end($segments), '`"');
    }

    /**
     * @return array<int, string>
     */
    private function declaredTables(PluginManifest $manifest): array
    {
        $tables = data_get($manifest->manifest, 'uninstall.tables', []);

        if (! is_array($tables)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $table): ?string => is_string($table) ? trim($table) : null,
            $tables,
        ))));
    }

    private function guardTableName(string $table): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException("Unsafe plugin table name [{$table}].");
        }

        if (in_array($table, $this->protectedTables(), true)) {
            throw new RuntimeException("Refusing to drop protected platform table [{$table}].");
        }
    }

    /**
     * @return array<int, string>
     */
    private function protectedTables(): array
    {
        return [
            'cache',
            'cache_locks',
            'documentation_tasks',
            'failed_jobs',
            'job_batches',
            'jobs',
            'migrations',
            'model_has_permissions',
            'model_has_roles',
            'password_reset_tokens',
            'permissions',
            'plugin_updates',
            'plugins',
            'role_has_permissions',
            'roles',
            'sessions',
            'users',
        ];
    }
}
