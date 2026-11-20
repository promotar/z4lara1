<?php

namespace Modules\Certificates;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CertificatesLifecycle
{
    public function grantAdministration(): void
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../module.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest['permissions'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Role::query()->where('guard_name', 'web')->whereIn('name', ['super-admin', 'admin'])->get() as $role) {
            $role->givePermissionTo($manifest['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
