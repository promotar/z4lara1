<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Core\Accounts\AccountSectionRegistry;
use App\Platform\Core\Hooks\HookManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_renders_core_data_without_plugin_tables(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('front.account'))
            ->assertOk()
            ->assertSee('My Account')
            ->assertSee($user->email);
    }

    public function test_registered_hook_can_add_an_isolated_account_tab(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        app(HookManager::class)->addFilter('frontend.account.sections', function (array $sections): array {
            $sections[] = [
                'key' => 'learning',
                'label' => 'Learning',
                'icon' => 'L',
                'order' => 20,
                'view' => 'frontend.account-sections.overview',
                'data' => [],
            ];

            return $sections;
        }, 10, 2);

        $sections = app(AccountSectionRegistry::class)->sections($user);

        $this->assertSame(['overview', 'learning'], array_column($sections, 'key'));
    }
}
