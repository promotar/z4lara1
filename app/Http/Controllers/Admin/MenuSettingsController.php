<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Logs\OperationLogger;
use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class MenuSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->initializeDefaults();

        $activeLocation = in_array($request->query('location'), ['frontend', 'admin'], true)
            ? (string) $request->query('location')
            : 'admin';
        $activeMenuId = (int) $request->query('menu', 0);
        $menus = Menu::query()
            ->with(['items' => fn ($query) => $query->orderByRaw('parent_id IS NOT NULL')->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')])
            ->whereIn('location', ['frontend', 'admin'])
            ->orderBy('location')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('location');

        return view('admin.menus.index', [
            'activeLocation' => $activeLocation,
            'activeMenuId' => $activeMenuId,
            'menus' => $menus,
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
            'routeNames' => collect(Route::getRoutes())
                ->map(fn ($route) => $route->getName())
                ->filter()
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    public function store(Request $request, string $location): RedirectResponse
    {
        abort_unless(in_array($location, ['frontend', 'admin'], true), 404);
        $menu = $this->menuFor($location);

        return $this->storeItem($request, $menu);
    }

    public function storeForMenu(Request $request, Menu $menu): RedirectResponse
    {
        abort_unless(in_array($menu->location, ['frontend', 'admin'], true), 404);

        return $this->storeItem($request, $menu);
    }

    public function storeMenu(Request $request, string $location): RedirectResponse
    {
        abort_unless(in_array($location, ['frontend', 'admin'], true), 404);

        $data = $this->validatedMenu($request);
        $key = $this->uniqueMenuKey(($data['key'] ?? null) ?: $data['name'], $location);

        $menu = Menu::query()->create([
            'key' => $key,
            'name' => $data['name'],
            'location' => $location,
            'description' => $data['description'] ?? null,
            'source' => 'platform',
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $operation = app(OperationLogger::class)->start('admin.menus.create', 'menu', (string) $menu->id, [
            'location' => $location,
            'key' => $menu->key,
            'name' => $menu->name,
        ], $request->user()?->id);
        app(OperationLogger::class)->success($operation, ucfirst($location).' menu created from menu settings.');

        return redirect()
            ->route('admin.menus.index', ['location' => $location, 'menu' => $menu->id])
            ->with('status', $location === 'admin' ? 'Admin section created.' : 'Frontend menu created.');
    }

    public function updateMenu(Request $request, Menu $menu): RedirectResponse
    {
        abort_unless(in_array($menu->location, ['frontend', 'admin'], true), 404);

        $data = $this->validatedMenu($request);

        if ($menu->location === 'admin'
            && ! (bool) ($data['is_active'] ?? false)
            && $menu->is_active
            && ! Menu::query()->where('location', 'admin')->where('is_active', true)->where('id', '!=', $menu->id)->exists()) {
            throw ValidationException::withMessages(['is_active' => 'At least one admin section must remain active.']);
        }

        $menu->update([
            'key' => $this->uniqueMenuKey(($data['key'] ?? null) ?: $data['name'], $menu->location, $menu->id),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $operation = app(OperationLogger::class)->start('admin.menus.update', 'menu', (string) $menu->id, [
            'location' => $menu->location,
            'key' => $menu->key,
            'name' => $menu->name,
        ], $request->user()?->id);
        app(OperationLogger::class)->success($operation, ucfirst($menu->location).' menu updated from menu settings.');

        return redirect()
            ->route('admin.menus.index', ['location' => $menu->location, 'menu' => $menu->id])
            ->with('status', $menu->location === 'admin' ? 'Admin section updated.' : 'Frontend menu updated.');
    }

    public function destroyMenu(Menu $menu): RedirectResponse
    {
        abort_unless(in_array($menu->location, ['frontend', 'admin'], true), 404);

        if ($menu->location === 'admin' && $menu->items()->exists()) {
            throw ValidationException::withMessages(['menu' => 'Move or delete every item before deleting an admin section.']);
        }

        if ($menu->location === 'admin' && Menu::query()->where('location', 'admin')->where('id', '!=', $menu->id)->doesntExist()) {
            throw ValidationException::withMessages(['menu' => 'The final admin section cannot be deleted.']);
        }

        $operation = app(OperationLogger::class)->start('admin.menus.delete', 'menu', (string) $menu->id, [
            'location' => $menu->location,
            'key' => $menu->key,
            'name' => $menu->name,
        ], request()->user()?->id);
        $menu->delete();
        app(OperationLogger::class)->success($operation, ucfirst($menu->location).' menu removed from menu settings.');

        return redirect()
            ->route('admin.menus.index', ['location' => $menu->location])
            ->with('status', $menu->location === 'admin' ? 'Admin section removed.' : 'Frontend menu removed.');
    }

    private function storeItem(Request $request, Menu $menu): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertAdminItemAllowed($data, $menu);

        $item = $menu->items()->create($this->itemPayload($data, $menu));

        $operation = app(OperationLogger::class)->start('admin.menus.items.create', 'menu-item', (string) $item->id, [
            'menu_id' => $menu->id,
            'menu_key' => $menu->key,
            'location' => $menu->location,
            'title' => $item->title,
        ], $request->user()?->id);
        app(OperationLogger::class)->success($operation, 'Menu item created from menu settings.');

        return redirect()
            ->route('admin.menus.index', ['location' => $menu->location, 'menu' => $menu->id])
            ->with('status', 'Menu item created.');
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $data = $this->validated($request);
        $menu = $this->adminDestinationMenu($data, $item->menu);

        if ($menu->id !== $item->menu_id) {
            $data['parent_id'] = null;
        }

        $this->assertAdminItemAllowed($data, $menu, $item);
        $item->update(['menu_id' => $menu->id] + $this->itemPayload($data, $menu, $item));

        $operation = app(OperationLogger::class)->start('admin.menus.items.update', 'menu-item', (string) $item->id, [
            'menu_id' => $item->menu_id,
            'location' => $item->menu->location,
            'title' => $item->title,
        ], $request->user()?->id);
        app(OperationLogger::class)->success($operation, 'Menu item updated from menu settings.');

        return redirect()
            ->route('admin.menus.index', ['location' => $item->menu->location, 'menu' => $item->menu_id])
            ->with('status', 'Menu item updated.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $location = $item->menu->location;
        $menuId = $item->menu_id;
        $itemId = $item->id;
        $title = $item->title;
        $operation = app(OperationLogger::class)->start('admin.menus.items.delete', 'menu-item', (string) $itemId, [
            'menu_id' => $menuId,
            'location' => $location,
            'title' => $title,
        ], request()->user()?->id);
        $item->delete();
        app(OperationLogger::class)->success($operation, 'Menu item removed from menu settings.');

        return redirect()
            ->route('admin.menus.index', ['location' => $location, 'menu' => $menuId])
            ->with('status', 'Menu item removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMenu(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:link,route,header'],
            'url' => ['nullable', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:24'],
            'target' => ['nullable', 'in:_self,_blank'],
            'permission' => ['nullable', 'string', 'exists:permissions,name'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'sort_order' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'css_class' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_:\\-\\s\\/\\[\\]\\.]*$/'],
            'text_color' => ['nullable', 'string', 'max:32'],
            'background_color' => ['nullable', 'string', 'max:32'],
            'hover_text_color' => ['nullable', 'string', 'max:32'],
            'hover_background_color' => ['nullable', 'string', 'max:32'],
            'font_weight' => ['nullable', 'in:normal,medium,semibold,bold'],
            'border_radius' => ['nullable', 'string', 'max:32'],
            'padding' => ['nullable', 'string', 'max:32'],
            'audience' => ['nullable', 'in:all,guest,authenticated'],
            'action_method' => ['nullable', 'in:get,post'],
            'destination_menu_id' => ['nullable', 'integer', 'exists:menus,id'],
        ]);

        $data['url'] = $this->nullableTrim($data['url'] ?? null);
        $data['route_name'] = $this->nullableTrim($data['route_name'] ?? null);
        $data['type'] = $this->normalizeItemType((string) $data['type'], $data['url'], $data['route_name']);
        $data['audience'] = (string) ($data['audience'] ?? 'all');
        $data['action_method'] = (string) ($data['action_method'] ?? 'get');

        $errors = [];

        if ($data['type'] === 'route') {
            if ($data['route_name'] === null) {
                $errors['route_name'] = 'Choose a route for this menu item.';
            } elseif (! Route::has($data['route_name'])) {
                $errors['route_name'] = 'The selected route is not registered.';
            } else {
                $method = strtoupper($data['action_method']);
                $routeMethods = Route::getRoutes()->getByName($data['route_name'])?->methods() ?? [];

                if (! in_array($method, $routeMethods, true)) {
                    $errors['action_method'] = 'The selected route does not accept '.$method.' requests.';
                }
            }
        }

        if ($data['type'] === 'link' && $data['url'] === null) {
            $errors['url'] = 'Enter a URL for this menu item.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function itemPayload(array $data, Menu $menu, ?MenuItem $item = null): array
    {
        $type = (string) $data['type'];
        $metadata = is_array($item?->metadata) ? $item->metadata : [];
        $metadata['style'] = $this->stylePayload($data);

        if ($menu->location === 'frontend') {
            $metadata['audience'] = (string) ($data['audience'] ?? 'all');
            $metadata['action_method'] = $type === 'route'
                ? (string) ($data['action_method'] ?? 'get')
                : 'get';
        }

        return [
            'title' => $data['title'],
            'label' => $data['label'] ?? null,
            'type' => $type,
            'url' => $type === 'link' ? ($data['url'] ?? null) : null,
            'route_name' => $type === 'route' ? ($data['route_name'] ?? null) : null,
            'icon' => $data['icon'] ?? null,
            'target' => $data['target'] ?? '_self',
            'permission' => $data['permission'] ?? null,
            'parent_id' => $this->resolvedParentId($data, $menu, $item),
            'metadata' => $metadata,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertAdminItemAllowed(array $data, Menu $menu, ?MenuItem $item = null): void
    {
        if ($menu->location !== 'admin' || ($data['type'] ?? null) !== 'route') {
            return;
        }

        $routeName = (string) ($data['route_name'] ?? '');

        if ($routeName !== 'dashboard' && ! str_starts_with($routeName, 'admin.')) {
            throw ValidationException::withMessages(['route_name' => 'Admin sections accept dashboard or admin.* routes only.']);
        }

        $duplicate = MenuItem::query()
            ->whereHas('menu', fn ($query) => $query->where('location', 'admin'))
            ->where('route_name', $routeName)
            ->when($item, fn ($query) => $query->where('id', '!=', $item->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['route_name' => 'This admin route is already registered in another menu section.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function adminDestinationMenu(array $data, Menu $current): Menu
    {
        if ($current->location !== 'admin' || empty($data['destination_menu_id'])) {
            return $current;
        }

        $destination = Menu::query()->where('location', 'admin')->find($data['destination_menu_id']);

        if (! $destination) {
            throw ValidationException::withMessages(['destination_menu_id' => 'Choose a registered admin section.']);
        }

        return $destination;
    }

    private function normalizeItemType(string $type, ?string $url, ?string $routeName): string
    {
        if ($type === 'header') {
            return 'header';
        }

        if ($type === 'route' && $routeName === null && $url !== null) {
            return 'link';
        }

        if ($type === 'link' && $url === null && $routeName !== null) {
            return 'route';
        }

        return $type;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stylePayload(array $data): array
    {
        return array_filter([
            'css_class' => $this->cleanClass((string) ($data['css_class'] ?? '')),
            'text_color' => $this->cleanColor((string) ($data['text_color'] ?? '')),
            'background_color' => $this->cleanColor((string) ($data['background_color'] ?? '')),
            'hover_text_color' => $this->cleanColor((string) ($data['hover_text_color'] ?? '')),
            'hover_background_color' => $this->cleanColor((string) ($data['hover_background_color'] ?? '')),
            'font_weight' => $this->cleanToken((string) ($data['font_weight'] ?? ''), ['normal', 'medium', 'semibold', 'bold']),
            'border_radius' => $this->cleanCssSize((string) ($data['border_radius'] ?? '')),
            'padding' => $this->cleanCssSizeList((string) ($data['padding'] ?? '')),
        ], fn (string $value): bool => $value !== '');
    }

    private function cleanClass(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_:\-\s\/\[\]\.]/', '', $value) ?? '');
    }

    private function cleanColor(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(transparent|currentColor|inherit)$/', $value) === 1) {
            return $value;
        }

        return '';
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function cleanToken(string $value, array $allowed): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function cleanCssSize(string $value): string
    {
        $value = trim($value);

        return preg_match('/^(0|[0-9]{1,3}(px|rem|em|%))$/', $value) === 1 ? $value : '';
    }

    private function cleanCssSizeList(string $value): string
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $this->cleanCssSize($part) !== ''));

        return count($parts) >= 1 && count($parts) <= 4 ? implode(' ', $parts) : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvedParentId(array $data, Menu $menu, ?MenuItem $item = null): ?int
    {
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;

        if ($parentId <= 0) {
            return null;
        }

        $parent = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->whereKey($parentId)
            ->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Choose a parent item from the same menu.',
            ]);
        }

        if ($item !== null && $parent->id === $item->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A menu item cannot be its own parent.',
            ]);
        }

        if ($item !== null && $this->isDescendantOf($parent, $item)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A menu item cannot be moved under one of its children.',
            ]);
        }

        return $parent->id;
    }

    private function isDescendantOf(MenuItem $candidate, MenuItem $ancestor): bool
    {
        $current = $candidate;
        $guard = 0;

        while ($current->parent_id !== null && $guard < 100) {
            if ((int) $current->parent_id === (int) $ancestor->id) {
                return true;
            }

            $current = MenuItem::query()->find($current->parent_id);

            if ($current === null) {
                return false;
            }

            $guard++;
        }

        return false;
    }

    public function initializeDefaults(): void
    {
        Menu::query()->firstOrCreate([
            'key' => 'platform.frontend',
            'location' => 'frontend',
        ], [
            'name' => 'Frontend Menu',
            'description' => 'Editable frontend navigation menu.',
            'source' => 'platform',
            'is_active' => true,
            'sort_order' => 0,
        ]);

    }

    private function menuFor(string $location): Menu
    {
        $this->initializeDefaults();

        return Menu::query()
            ->where('location', $location)
            ->when($location === 'frontend', fn ($query) => $query->where('key', 'platform.frontend'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();
    }

    private function uniqueMenuKey(string $value, string $location, ?int $exceptId = null): string
    {
        $base = 'platform.'.trim(strtolower(preg_replace('/[^A-Za-z0-9_.:-]+/', '-', $value) ?? 'menu'), '-');
        $base = $base !== 'platform.' ? $base : 'platform.menu';
        $key = $base;
        $index = 2;

        while (
            Menu::query()
                ->where('location', $location)
                ->where('key', $key)
                ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $key = $base.'-'.$index;
            $index++;
        }

        return $key;
    }
}
