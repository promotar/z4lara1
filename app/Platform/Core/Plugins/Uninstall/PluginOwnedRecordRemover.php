<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PluginOwnedRecordRemover
{
    /**
     * @return array<string, int>
     */
    public function remove(PluginManifest $manifest): array
    {
        $removed = [];

        foreach ((array) data_get($manifest->manifest, 'uninstall.records', []) as $definition) {
            $table = (string) data_get($definition, 'table');
            $column = (string) data_get($definition, 'column');
            $values = array_values((array) data_get($definition, 'values', []));

            $this->guard($table, $column, $values);

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $removed[$table.'.'.$column] = DB::table($table)
                ->whereIn($column, $values)
                ->delete();
        }

        return $removed;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function guard(string $table, string $column, array $values): void
    {
        if (
            preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
            || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1
            || $values === []
            || collect($values)->contains(fn (mixed $value): bool => ! is_string($value) && ! is_int($value))
        ) {
            throw new RuntimeException('Plugin declares an unsafe owned-record purge rule.');
        }
    }
}
