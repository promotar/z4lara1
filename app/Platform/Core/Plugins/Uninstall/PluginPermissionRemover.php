<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\DTOs\PluginManifest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PluginPermissionRemover
{
    /**
     * @return array<int, string>
     */
    public function remove(PluginManifest $manifest): array
    {
        if (! class_exists(Permission::class)) {
            return [];
        }

        $query = Permission::query()->where('guard_name', 'web');
        $declared = $this->declaredPermissions($manifest);
        $routePermissions = $this->declaredRoutePermissions($manifest);

        $query->where(function ($permissions) use ($declared, $manifest, $routePermissions): void {
            if ($declared !== []) {
                $permissions->whereIn('name', $declared);
            }

            $permissions->orWhere('name', 'like', $manifest->slug.'.%');

            foreach ($routePermissions['exact'] as $permission) {
                $permissions->orWhere('name', $permission);
            }

            foreach ($routePermissions['prefixes'] as $prefix) {
                $permissions->orWhere('name', 'like', $prefix.'%');
            }
        });

        $removed = $query->pluck('name')->all();

        if ($removed !== []) {
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $removed)
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return array_values($removed);
    }

    /**
     * @return array<int, string>
     */
    private function declaredPermissions(PluginManifest $manifest): array
    {
        $permissions = data_get($manifest->manifest, 'permissions', []);

        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function (mixed $permission): ?string {
            if (is_string($permission)) {
                return $permission;
            }

            if (is_array($permission) && isset($permission['name'])) {
                return (string) $permission['name'];
            }

            return null;
        }, $permissions))));
    }

    /**
     * @return array{exact: array<int, string>, prefixes: array<int, string>}
     */
    private function declaredRoutePermissions(PluginManifest $manifest): array
    {
        $exact = [];
        $prefixes = [];

        foreach ((array) data_get($manifest->manifest, 'registry.routes', []) as $routeName => $metadata) {
            if (is_string($routeName) && $this->routeBelongsToPlugin($routeName, $manifest->slug)) {
                $exact[] = 'route.'.$routeName;
            }
        }

        foreach ((array) data_get($manifest->manifest, 'routes', []) as $route) {
            if (! is_array($route)) {
                continue;
            }

            $routeName = $route['name'] ?? null;

            if (! is_string($routeName) || ! $this->routeBelongsToPlugin($routeName, $manifest->slug)) {
                continue;
            }

            $permission = 'route.'.$routeName;

            if (str_ends_with($routeName, '.')) {
                $prefixes[] = $permission;
            } else {
                $exact[] = $permission;
            }
        }

        return [
            'exact' => array_values(array_unique($exact)),
            'prefixes' => array_values(array_unique($prefixes)),
        ];
    }

    private function routeBelongsToPlugin(string $routeName, string $slug): bool
    {
        if (preg_match('/^[A-Za-z0-9_.:-]+$/', $routeName) !== 1) {
            return false;
        }

        return in_array($slug, explode('.', trim($routeName, '.')), true);
    }
}
