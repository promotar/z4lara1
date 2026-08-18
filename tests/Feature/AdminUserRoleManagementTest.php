<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use App\Platform\Core\Services\PermissionManager;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminUserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_editor_exposes_roles_and_manual_activation_without_direct_permissions(): void
    {
        $source = file_get_contents(resource_path('views/admin/users/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('name="roles[]"', $source);
        $this->assertStringContainsString('Activate User', $source);
        $this->assertStringNotContainsString('Direct Permissions', $source);
        $this->assertStringNotContainsString('name="permissions[]"', $source);
    }

    public function test_updating_a_user_does_not_remove_legacy_direct_permissions(): void
    {
        app(PermissionManager::class)->syncDefaults();

        $permission = Permission::findOrCreate('legacy.direct-access', 'web');
        $user = User::factory()->create();
        $user->assignRole('user');
        $user->givePermissionTo($permission);

        $request = Request::create('/admin/users/'.$user->id, 'PATCH', [
            'name' => 'Updated User',
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => ['user'],
            'permissions' => [],
        ]);

        app(UserController::class)->update($request, $user);

        $this->assertSame('Updated User', $user->fresh()->name);
        $this->assertTrue($user->fresh()->hasDirectPermission($permission));
    }

    public function test_creating_a_user_ignores_direct_permission_input(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $permission = Permission::findOrCreate('legacy.direct-access', 'web');

        $request = Request::create('/admin/users', 'POST', [
            'name' => 'Role Only User',
            'email' => 'role-only@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'roles' => ['user'],
            'permissions' => [$permission->name],
        ]);

        app(UserController::class)->store($request, app(SettingsRepository::class));

        $user = User::where('email', 'role-only@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasDirectPermission($permission));
    }

    public function test_an_administrator_can_activate_an_unverified_user_manually(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        app(UserController::class)->verifyEmail($user);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
