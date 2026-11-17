<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PluginOwnedColumnRemover
{
    /**
     * @return array<int, string>
     */
    public function remove(PluginManifest $manifest): array
    {
        $removed = [];

        foreach ((array) data_get($manifest->manifest, 'uninstall.columns', []) as $definition) {
            $table = (string) data_get($definition, 'table');
            $columns = array_values((array) data_get($definition, 'columns', []));

            $this->guard($table, $columns);

            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($existing === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($existing): void {
                $blueprint->dropColumn($existing);
            });

            foreach ($existing as $column) {
                $removed[] = $table.'.'.$column;
            }
        }

        return $removed;
    }

    /**
     * @param  array<int, mixed>  $columns
     */
    private function guard(string $table, array $columns): void
    {
        $protected = ['id', 'email', 'password', 'created_at', 'updated_at'];

        if (
            preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
            || $columns === []
            || collect($columns)->contains(
                fn (mixed $column): bool => ! is_string($column)
                    || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1
                    || in_array($column, $protected, true),
            )
        ) {
            throw new RuntimeException('Plugin declares an unsafe owned-column purge rule.');
        }
    }
}
