<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Services\PermissionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(PermissionManager $permissionManager): View
    {
        return view('admin.permissions.index', [
            'permissions' => Permission::orderBy('name')->get(),
            'defaultPermissions' => $permissionManager->permissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:permissions,name'],
        ]);

        Permission::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'تم إنشاء الصلاحية.');
    }

    public function syncDefaults(PermissionManager $permissionManager): RedirectResponse
    {
        $permissionManager->syncDefaults();

        return back()->with('status', 'تمت مزامنة الصلاحيات والأدوار الافتراضية.');
    }
}
