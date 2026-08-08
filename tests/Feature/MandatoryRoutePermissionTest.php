<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Core\Access\RoutePermissionCatalog;
use App\Platform\Core\Services\PermissionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MandatoryRoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_without_exact_route_permission_is_denied(): void
    {
        $user = $this->adminUser();
        app(RoutePermissionCatalog::class)->sync();

        $this->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_exact_route_permission_is_checked_on_every_request(): void
    {
        $user = $this->adminUser();
        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync();

        $this->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $user->givePermissionTo($catalog->permissionName('admin.settings.index'));

        $this->actingAs($user->fresh())
            ->get(route('admin.settings.index'))
            ->assertOk();
    }

    public function test_dashboard_hides_routes_that_are_not_assigned(): void
    {
        $user = $this->adminUser();
        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync();
        $user->givePermissionTo($catalog->permissionName('dashboard'));

        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('admin.settings.index'), false);

        $user->givePermissionTo($catalog->permissionName('admin.settings.index'));

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.settings.index'), false);
    }

    public function test_exact_route_permission_does_not_bypass_capability_middleware(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync();
        $user->givePermissionTo($catalog->permissionName('admin.settings.index'));

        $this->actingAs($user->fresh())
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_unassigned_form_action_is_removed_from_an_allowed_page(): void
    {
        $user = $this->adminUser();
        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync();
        $user->givePermissionTo($catalog->permissionName('admin.settings.index'));

        $this->actingAs($user->fresh())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('method="POST" action="'.route('admin.settings.update').'"', false);
    }

    public function test_roles_page_syncs_and_displays_active_routes_automatically(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('route.admin.roles.index');

        $this->assertDatabaseHas('permissions', [
            'name' => 'route.admin.roles.index',
            'guard_name' => 'web',
        ]);
    }

    public function test_existing_access_migration_never_promotes_frontend_roles_to_admin(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $student = Role::findOrCreate('student', 'web');
        $student->givePermissionTo('pages.manage');

        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync(true);

        $this->assertFalse($student->fresh()->hasPermissionTo($catalog->permissionName('dashboard')));
        $this->assertFalse($student->fresh()->hasPermissionTo($catalog->permissionName('admin.settings.index')));
    }

    public function test_non_super_admin_cannot_assign_the_super_admin_role(): void
    {
        $actor = $this->adminUser();
        $target = User::factory()->create(['email_verified_at' => now()]);
        $target->assignRole('user');
        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync();
        $actor->givePermissionTo($catalog->permissionName('admin.users.update'));

        $this->actingAs($actor->fresh())
            ->patch(route('admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => ['super-admin'],
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('super-admin'));
    }

    public function test_the_final_super_admin_assignment_cannot_be_removed(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $superAdmin = User::role('super-admin')->first();

        if ($superAdmin === null) {
            $superAdmin = User::factory()->create(['email_verified_at' => now()]);
            $superAdmin->assignRole('super-admin');
        }

        $this->assertSame(1, User::role('super-admin')->count());

        $this->actingAs($superAdmin)
            ->patch(route('admin.users.update', $superAdmin), [
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'roles' => ['admin'],
                'permissions' => [],
            ])
            ->assertStatus(422);

        $this->assertTrue($superAdmin->fresh()->hasRole('super-admin'));
    }

    private function adminUser(): User
    {
        app(PermissionManager::class)->syncDefaults();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }
}
