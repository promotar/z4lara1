<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAccountMenuRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_account_is_registered_once_in_the_overview_menu(): void
    {
        $item = DB::table('menu_items')->where('route_name', 'front.account')->first();

        $this->assertNotNull($item);
        $this->assertSame('platform.admin.overview', DB::table('menus')->where('id', $item->menu_id)->value('key'));

        $this->migration()->up();

        $this->assertSame(1, DB::table('menu_items')->where('route_name', 'front.account')->count());
    }

    public function test_rerunning_the_registration_preserves_admin_customizations(): void
    {
        $itemId = DB::table('menu_items')->where('route_name', 'front.account')->value('id');
        $this->assertNotNull($itemId);

        DB::table('menu_items')->where('id', $itemId)->update([
            'label' => 'Private Profile',
            'icon' => 'P',
            'is_active' => false,
            'sort_order' => 87,
        ]);

        $this->migration()->up();

        $item = DB::table('menu_items')->where('id', $itemId)->first();
        $this->assertSame('Private Profile', $item->label);
        $this->assertSame('P', $item->icon);
        $this->assertSame(0, (int) $item->is_active);
        $this->assertSame(87, (int) $item->sort_order);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_03_000001_register_my_account_admin_menu_item.php');
    }
}
