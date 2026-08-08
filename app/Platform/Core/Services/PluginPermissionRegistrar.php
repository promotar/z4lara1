<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PluginPermissionRegistrar
{
    /**
     * @return array<int, string>
     */
    public function register(PluginManifest $manifest): array
    {
        if (! class_exists(Permission::class)) {
            return [];
        }

        $created = [];

        $permissions = $this->permissions($manifest);

        foreach ($permissions as $permission) {
            $model = Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);

            if ($model->wasRecentlyCreated) {
                $created[] = $permission;
            }
        }

        if ($permissions !== [] && class_exists(Role::class)) {
            Role::firstOrCreate([
                'name' => 'super-admin',
                'guard_name' => 'web',
            ])->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }

    /**
     * @param array<int, string> $permissions
     */
    public function unregisterCreated(array $permissions): void
    {
        if ($permissions === [] || ! class_exists(Permission::class)) {
            return;
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    private function permissions(PluginManifest $manifest): array
    {
        $permissions = data_get($manifest->manifest, 'permissions', []);

        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $permission): ?string {
            if (is_string($permission)) {
                return $permission;
            }

            if (is_array($permission) && isset($permission['name'])) {
                return (string) $permission['name'];
            }

            return null;
        }, $permissions)));
    }
}
