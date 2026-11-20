<?php

namespace Modules\Store\Infrastructure\Lifecycle;

use Illuminate\Support\Facades\Schema;
use Modules\Store\Application\PageBuilder\StorePageBuilderPageService;
use Modules\Store\Application\Storefront\StoreFrontendMenuService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Core executes lifecycle callbacks before an inactive plugin provider is
// registered. Load this plugin-owned dependency here so constructor injection
// works during install, activation, deactivation, and purge alike.
require_once dirname(__DIR__, 2).'/Application/PageBuilder/StorePageBuilderPageService.php';
require_once dirname(__DIR__, 2).'/Application/Storefront/StoreFrontendMenuService.php';

/**
 * Store-owned lifecycle callbacks. Runtime gating, audit logs, locks, routes,
 * menus, and cache rebuilding remain responsibilities of the platform Core.
 */
final class StoreLifecycle
{
    /** @var array<string, string> */
    private const ADMIN_ROUTE_CAPABILITIES = [
        'route.admin.plugins.store.settings.index' => 'store.settings.view',
        'route.admin.plugins.store.settings.currency-tax' => 'store.settings.view',
        'route.admin.plugins.store.settings.pages' => 'store.settings.view',
        'route.admin.plugins.store.settings.inventory' => 'store.settings.view',
        'route.admin.plugins.store.settings.orders' => 'store.settings.view',
        'route.admin.plugins.store.settings.shipping-countries' => 'store.settings.view',
        'route.admin.plugins.store.settings.cash-on-delivery' => 'store.settings.view',
        'route.admin.plugins.store.settings.notifications' => 'store.settings.view',
        'route.admin.plugins.store.settings.policies' => 'store.settings.view',
        'route.admin.plugins.store.settings.update' => 'store.settings.update',
        'route.admin.plugins.store.templates.index' => 'store.templates.manage',
        'route.admin.plugins.store.templates.catalog' => 'store.templates.manage',
        'route.admin.plugins.store.templates.store' => 'store.templates.manage',
        'route.admin.plugins.store.templates.edit' => 'store.templates.manage',
        'route.admin.plugins.store.templates.update' => 'store.templates.manage',
        'route.admin.plugins.store.templates.destroy' => 'store.templates.manage',
        'route.admin.plugins.store.templates.duplicate' => 'store.templates.manage',
        'route.admin.plugins.store.categories.index' => 'store.categories.view',
        'route.admin.plugins.store.categories.index.edit' => 'store.categories.manage',
        'route.admin.plugins.store.categories.store' => 'store.categories.manage',
        'route.admin.plugins.store.categories.update' => 'store.categories.manage',
        'route.admin.plugins.store.categories.image' => 'store.categories.manage',
    ];

    public function __construct(
        private readonly StoreFrontendMenuService $frontendMenu,
        private readonly StorePageBuilderPageService $storePage,
    ) {}

    public function install(): void
    {
        $this->storePage->ensure();
        $this->frontendMenu->sync(false);
        $this->syncAdministrativePermissions();
    }

    public function activate(): void
    {
        $this->storePage->ensure();
        $this->frontendMenu->sync(true);
        $this->syncAdministrativePermissions();
    }

    public function deactivate(): void
    {
        $this->frontendMenu->sync(false);
    }

    public function reactivate(): void
    {
        $this->activate();
    }

    public function uninstall(): void
    {
        $this->frontendMenu->sync(false);
    }

    public function purge(): void
    {
        $this->storePage->purge();
        // Store-owned tables are purged by Core from the manifest contract.
    }

    private function syncAdministrativePermissions(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::ADMIN_ROUTE_CAPABILITIES as $routePermissionName => $capabilityName) {
            $routePermission = Permission::findOrCreate($routePermissionName, 'web');
            $capability = Permission::query()
                ->where('name', $capabilityName)
                ->where('guard_name', 'web')
                ->first();

            if ($capability === null) {
                continue;
            }

            foreach ($capability->roles as $role) {
                $role->givePermissionTo($routePermission);
            }
        }

        // Artwork authoring belongs to the Exhibitions plugin. Remove the old
        // Store-owned artist grant while retaining Store's sales contract and
        // historical tables for backward-compatible order records.
        $artistRole = Role::query()->where('name', 'artist')->where('guard_name', 'web')->first();
        if ($artistRole !== null) {
            $artistRole->revokePermissionTo(Permission::findOrCreate('store.artworks.sell', 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
