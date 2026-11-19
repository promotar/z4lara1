<?php

use App\Platform\Core\Menus\MenuDefaults;
use App\Platform\Core\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the core language switcher items to the header/frontend menu so
 * existing installations get the same default as a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = MenuDefaults::defaultLanguageMenu();

        if ($menu === null) {
            $menu = Menu::query()->firstOrCreate(
                ['key' => 'platform.frontend', 'location' => 'frontend'],
                [
                    'name' => 'Frontend Menu',
                    'description' => 'Editable frontend navigation menu.',
                    'source' => 'platform',
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
        }

        MenuDefaults::ensureLanguageItems($menu);
    }

    public function down(): void
    {
        // Menu content is user-visible; keep it on rollback.
    }
};
