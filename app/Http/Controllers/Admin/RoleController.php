<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Access\RoutePermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(RoutePermissionCatalog $routes): View
    {
        $routes->sync();
        $routePermissions = $routes->routes();
        $routePermissionNames = $routePermissions->pluck('permission');

        return view('admin.roles.index', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissions' => Permission::whereNotIn('name', $routePermissionNames)->orderBy('name')->get(),
            'routePermissions' => $routePermissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ])->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', __('core.roles.flash.created'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'super-admin' && $request->string('name')->toString() !== 'super-admin') {
            return back()->withErrors([
                'role' => __('core.roles.validation.super_admin_name_locked'),
            ]);
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('roles', 'name')
                    ->where('guard_name', $role->guard_name)
                    ->ignore($role->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->forceFill([
            'name' => $data['name'],
        ])->save();

        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', __('core.roles.flash.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'super-admin') {
            return back()->withErrors([
                'role' => __('core.roles.validation.super_admin_delete_locked'),
            ]);
        }

        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => __('core.roles.validation.role_in_use'),
            ]);
        }

        $role->syncPermissions([]);
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', __('core.roles.flash.deleted'));
    }
}
