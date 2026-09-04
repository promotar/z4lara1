<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Core\Access\RoutePermissionCatalog;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Services\PermissionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_delete_an_unrelated_user(): void
    {
        [$actor, $target] = $this->users();

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_current_user_and_final_super_administrator_are_protected(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $actor = User::role('super-admin')->first();
        if ($actor === null) {
            $actor = User::factory()->create(['email_verified_at' => now()]);
            $actor->assignRole('super-admin');
        }

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $actor))
            ->assertSessionHasErrors('user');

        $secondActor = User::factory()->create(['email_verified_at' => now()]);
        $secondActor->assignRole('admin');
        $catalog = app(RoutePermissionCatalog::class);
        $catalog->sync();
        $secondActor->givePermissionTo($catalog->permissionName('admin.users.destroy'));

        $this->actingAs($secondActor)
            ->delete(route('admin.users.destroy', $actor))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_plugin_blocker_prevents_user_deletion(): void
    {
        [$actor, $target] = $this->users();

        Plugin::query()->create([
            'name' => 'Protected Records',
            'slug' => 'protected-records',
            'version' => '1.0.0',
            'provider' => 'Tests\\Feature\\ProtectedRecordsProvider',
            'status' => Plugin::STATUS_ACTIVE,
            'manifest' => ['type' => 'feature', 'integrations' => ['user_deletion' => ['mode' => 'participant']]],
        ]);
        $this->app->singleton(BlockingDeletionParticipant::class);
        $this->app->tag(BlockingDeletionParticipant::class, UserDeletionParticipant::class);

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_user_without_management_permission_cannot_delete_an_account(): void
    {
        app(PermissionManager::class)->syncDefaults();
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->assignRole('user');
        $target = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_inactive_or_uninspectable_plugin_fails_closed(): void
    {
        [$actor, $target] = $this->users();

        Plugin::query()->create([
            'name' => 'Dormant Records',
            'slug' => 'dormant-records',
            'version' => '1.0.0',
            'provider' => 'Tests\\Feature\\DormantRecordsProvider',
            'status' => Plugin::STATUS_DISABLED,
            'manifest' => ['type' => 'feature', 'integrations' => ['user_deletion' => ['mode' => 'participant']]],
        ]);

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    /** @return array{User, User} */
    private function users(): array
    {
        app(PermissionManager::class)->syncDefaults();
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->assignRole('super-admin');
        $target = User::factory()->create(['email_verified_at' => now()]);
        $target->assignRole('user');

        return [$actor, $target];
    }
}

final class BlockingDeletionParticipant implements UserDeletionParticipant
{
    public function pluginSlug(): string
    {
        return 'protected-records';
    }

    public function blockers(User $user): array
    {
        return ['The user owns protected historical records.'];
    }

    public function cleanup(User $user): void {}
}
