<?php

namespace Modules\Store\Application\Storefront;

use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\MenuItem;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Facades\Route;
use Modules\Store\Application\PageBuilder\StorePageBuilderPageService;

final readonly class StoreFrontendMenuService
{
    public function __construct(
        private PluginRepository $plugins,
        private StorePageBuilderPageService $storePage,
    ) {}

    public function sync(bool $active): void
    {
        $plugin = $this->plugins->findBySlug('store');
        $menu = Menu::query()
            ->where('key', 'platform.frontend')
            ->where('location', 'frontend')
            ->first();

        if ($plugin === null || $menu === null) {
            return;
        }

        $pageSlug = $this->storePage->managedSlug();
        $routeName = $pageSlug !== null && Route::has('pages.show') ? 'pages.show' : 'store.index';
        $routeParams = $routeName === 'pages.show' ? ['slug' => $pageSlug] : null;
        $item = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('plugin_id', $plugin->id)
            ->where(function ($query): void {
                $query->where('route_name', 'store.index')
                    ->orWhere(function ($page): void {
                        $page->where('route_name', 'pages.show')
                            ->where('route_params->slug', StorePageBuilderPageService::SLUG);
                    });
            })
            ->orderBy('id')
            ->first();

        $values = [
                'route_name' => $routeName,
                'route_params' => $routeParams,
                'url' => null,
                'type' => 'route',
                'parent_id' => null,
                'metadata' => [
                    'owner' => 'store',
                    'contribution' => 'platform.frontend',
                    'managed_page' => $pageSlug,
                ],
                'is_active' => $active,
        ];

        if ($item !== null) {
            $item->fill($values)->save();

            return;
        }

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'plugin_id' => $plugin->id,
            'title' => 'المتجر',
            'label' => 'المتجر',
            'icon' => null,
            'target' => '_self',
            'permission' => null,
            'sort_order' => 70,
            ...$values,
        ]);
    }
}
