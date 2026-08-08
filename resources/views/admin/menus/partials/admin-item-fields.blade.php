@php
    $itemType = old('type', $item->type ?? 'route');
    $isActive = old('is_active', $item->is_active ?? true);
    $parentChoices = $parentChoices ?? collect();
@endphp

<div x-data="{ itemType: @js($itemType) }" class="admin-menu-compact-item-form">
    <label class="admin-field admin-menu-title-field">
        <span class="admin-field-label">Display name</span>
        <input name="title" value="{{ old('title', $item->title ?? '') }}" required class="admin-input">
    </label>

    <label class="admin-field admin-menu-type-field">
        <span class="admin-field-label">Type</span>
        <select name="type" x-model="itemType" class="admin-input">
            <option value="route">Route</option>
            <option value="link">Link</option>
            <option value="header">Submenu label</option>
        </select>
    </label>

    <label class="admin-field admin-menu-section-field">
        <span class="admin-field-label">Section</span>
        <select name="destination_menu_id" class="admin-input">
            @foreach ($activeMenus as $adminMenu)
                <option value="{{ $adminMenu->id }}" @selected((int) old('destination_menu_id', $item->menu_id ?? $activeMenu->id) === (int) $adminMenu->id)>
                    {{ $adminMenu->name }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="admin-field admin-menu-route-field" x-show="itemType === 'route'">
        <span class="admin-field-label">Registered admin route</span>
        <select name="route_name" class="admin-input">
            <option value="">Choose route</option>
            @foreach ($routeNames->filter(fn ($routeName) => $routeName === 'dashboard' || str_starts_with($routeName, 'admin.')) as $routeName)
                <option value="{{ $routeName }}" @selected(old('route_name', $item->route_name ?? '') === $routeName)>{{ $routeName }}</option>
            @endforeach
        </select>
    </label>

    <label class="admin-field admin-menu-route-field" x-show="itemType === 'link'">
        <span class="admin-field-label">URL</span>
        <input name="url" value="{{ old('url', $item->url ?? '') }}" placeholder="/admin/custom" class="admin-input">
    </label>

    <label class="admin-field admin-menu-parent-field">
        <span class="admin-field-label">Parent</span>
        <select name="parent_id" class="admin-input">
            <option value="">Top level</option>
            @foreach ($parentChoices as $choice)
                @continue($item && (int) $choice['item']->id === (int) $item->id)
                <option value="{{ $choice['item']->id }}" @selected((int) old('parent_id', $item->parent_id ?? 0) === (int) $choice['item']->id)>
                    {{ str_repeat('— ', (int) $choice['depth']) }}{{ $choice['item']->title }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="admin-field admin-menu-permission-field">
        <span class="admin-field-label">Permission</span>
        <select name="permission" class="admin-input">
            <option value="">No permission</option>
            @foreach ($permissions as $permission)
                <option value="{{ $permission }}" @selected(old('permission', $item->permission ?? '') === $permission)>{{ $permission }}</option>
            @endforeach
        </select>
    </label>

    <label class="admin-field admin-menu-icon-field">
        <span class="admin-field-label">Icon</span>
        <input name="icon" value="{{ old('icon', $item->icon ?? '') }}" maxlength="24" class="admin-input">
    </label>

    <label class="admin-field admin-menu-sort-field">
        <span class="admin-field-label">Order</span>
        <input name="sort_order" type="number" value="{{ old('sort_order', $item->sort_order ?? 0) }}" class="admin-input">
    </label>

    <label class="admin-menu-check-option admin-menu-compact-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked((bool) $isActive)>
        <span>Active</span>
    </label>

    <input type="hidden" name="label" value="{{ old('label', $item->label ?? '') }}">
    <input type="hidden" name="target" value="_self">
</div>
