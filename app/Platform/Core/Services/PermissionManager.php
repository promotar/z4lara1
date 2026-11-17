<?php

namespace App\Platform\Core\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionManager
{
    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'documentation.manage' => 'Manage admin documentation and task checklist',
            'settings.manage' => 'Manage platform settings',
            'media.manage' => 'Manage media library',
            'menus.manage' => 'Manage frontend and admin menu items',
            'pages.manage' => 'Manage content pages',
            'theme-builder.manage' => 'Manage theme builder layout parts',
            'plugins.view' => 'View installed plugins',
            'plugins.install' => 'Install plugins',
            'plugins.activate' => 'Activate and deactivate plugins',
            'users.manage' => 'Create and update users',
            'roles.manage' => 'Create and update roles',
            'permissions.manage' => 'Create and review permissions',
            'platform-registry.view' => 'View platform registry, logs, reports, and backups',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rolePermissions(): array
    {
        $allPermissions = array_keys($this->permissions());
        $adminPermissions = array_values(array_diff($allPermissions, ['platform-registry.view']));

        return [
            'super-admin' => $allPermissions,
            'admin' => $adminPermissions,
            'staff' => [
                'documentation.manage',
                'settings.manage',
                'plugins.view',
                'pages.manage',
            ],
            'employee' => [
                'documentation.manage',
            ],
        ];
    }

    /**
     * @return Collection<int, Permission>
     */
    public function syncDefaults(): Collection
    {
        $permissions = collect($this->permissions())->map(function (string $description, string $name): Permission {
            return Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        });

        foreach ($this->rolePermissions() as $roleName => $permissionNames) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ])->syncPermissions($permissionNames);
        }

        Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissions->values();
    }

    public function assignSuperAdmin(User $user): void
    {
        $this->syncDefaults();

        $user->assignRole('super-admin');
    }
}
