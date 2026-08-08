@php
    $itemType = old('type', $item->type ?? 'route');
    $isActive = old('is_active', $item->is_active ?? true);
    $style = is_array($item?->metadata ?? null) ? ($item->metadata['style'] ?? []) : [];
    $audience = old('audience', data_get($item?->metadata, 'audience', 'all'));
    $actionMethod = old('action_method', data_get($item?->metadata, 'action_method', 'get'));
    $parentChoices = $parentChoices ?? collect();
@endphp

<div x-data="{ itemType: @js($itemType) }" class="admin-menu-compact-item-form frontend-menu-compact-item-form">
    <label class="admin-field frontend-menu-title-field">
        <span class="admin-field-label">Display name</span>
        <input name="title" value="{{ old('title', $item->title ?? '') }}" required class="admin-input">
    </label>

    <label class="admin-field frontend-menu-label-field">
        <span class="admin-field-label">Menu label</span>
        <input name="label" value="{{ old('label', $item->label ?? '') }}" class="admin-input">
    </label>

    <label class="admin-field frontend-menu-parent-field">
        <span class="admin-field-label">Parent / submenu</span>
        <select name="parent_id" class="admin-input">
            <option value="">Main item</option>
            @foreach ($parentChoices as $choice)
                @continue($item && (int) $choice['item']->id === (int) $item->id)
                <option value="{{ $choice['item']->id }}" @selected((int) old('parent_id', $item->parent_id ?? 0) === (int) $choice['item']->id)>
                    {{ str_repeat('— ', (int) $choice['depth']) }}{{ $choice['item']->title }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="admin-field frontend-menu-type-field">
        <span class="admin-field-label">Type</span>
        <select name="type" x-model="itemType" class="admin-input">
            <option value="route">Route</option>
            <option value="link">Link</option>
            <option value="header">Parent label</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-target-field">
        <span class="admin-field-label">Target</span>
        <select name="target" class="admin-input">
            <option value="_self" @selected(old('target', $item->target ?? '_self') === '_self')>Same tab</option>
            <option value="_blank" @selected(old('target', $item->target ?? '_self') === '_blank')>New tab</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-destination-field" x-show="itemType === 'route'">
        <span class="admin-field-label">Registered route</span>
        <select name="route_name" class="admin-input">
            <option value="">Choose route</option>
            @foreach ($routeNames as $routeName)
                <option value="{{ $routeName }}" @selected(old('route_name', $item->route_name ?? '') === $routeName)>{{ $routeName }}</option>
            @endforeach
        </select>
    </label>

    <label class="admin-field frontend-menu-destination-field" x-show="itemType === 'link'">
        <span class="admin-field-label">URL</span>
        <input name="url" value="{{ old('url', $item->url ?? '') }}" placeholder="/pages/example" class="admin-input">
    </label>

    <div class="admin-menu-note admin-menu-note-warning frontend-menu-destination-field" x-show="itemType === 'header'">
        Parent label without a direct link.
    </div>

    <label class="admin-field frontend-menu-audience-field">
        <span class="admin-field-label">Audience</span>
        <select name="audience" class="admin-input">
            <option value="all" @selected($audience === 'all')>Everyone</option>
            <option value="guest" @selected($audience === 'guest')>Guests</option>
            <option value="authenticated" @selected($audience === 'authenticated')>Signed in</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-action-field">
        <span class="admin-field-label">Interaction</span>
        <select name="action_method" class="admin-input">
            <option value="get" @selected($actionMethod === 'get')>Navigate</option>
            <option value="post" @selected($actionMethod === 'post')>POST action</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-permission-field">
        <span class="admin-field-label">Permission</span>
        <select name="permission" class="admin-input">
            <option value="">No permission</option>
            @foreach ($permissions as $permission)
                <option value="{{ $permission }}" @selected(old('permission', $item->permission ?? '') === $permission)>{{ $permission }}</option>
            @endforeach
        </select>
    </label>

    <label class="admin-field frontend-menu-icon-field">
        <span class="admin-field-label">Icon</span>
        <input name="icon" value="{{ old('icon', $item->icon ?? '') }}" maxlength="24" class="admin-input">
    </label>

    <label class="admin-field frontend-menu-sort-field">
        <span class="admin-field-label">Order</span>
        <input name="sort_order" type="number" value="{{ old('sort_order', $item->sort_order ?? 0) }}" class="admin-input">
    </label>

    <label class="admin-menu-check-option admin-menu-compact-check frontend-menu-active-field">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked((bool) $isActive)>
        <span>Active</span>
    </label>

    <details class="frontend-menu-advanced">
        <summary>Optional display styling</summary>
        <div class="frontend-menu-style-grid">
            <label class="admin-field">
                <span class="admin-field-label">CSS Classes</span>
                <input name="css_class" value="{{ old('css_class', $style['css_class'] ?? '') }}" class="admin-input">
            </label>
            <label class="admin-field">
                <span class="admin-field-label">Font Weight</span>
                <select name="font_weight" class="admin-input">
                    <option value="">Default</option>
                    @foreach (['normal' => 'Normal', 'medium' => 'Medium', 'semibold' => 'Semibold', 'bold' => 'Bold'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('font_weight', $style['font_weight'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field"><span class="admin-field-label">Text</span><input type="color" name="text_color" value="{{ old('text_color', $style['text_color'] ?? '#334155') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label">Background</span><input type="color" name="background_color" value="{{ old('background_color', $style['background_color'] ?? '#ffffff') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label">Hover Text</span><input type="color" name="hover_text_color" value="{{ old('hover_text_color', $style['hover_text_color'] ?? '#0f172a') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label">Hover Background</span><input type="color" name="hover_background_color" value="{{ old('hover_background_color', $style['hover_background_color'] ?? '#f8fafc') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label">Border Radius</span><input name="border_radius" value="{{ old('border_radius', $style['border_radius'] ?? '') }}" placeholder="6px" class="admin-input"></label>
            <label class="admin-field"><span class="admin-field-label">Padding</span><input name="padding" value="{{ old('padding', $style['padding'] ?? '') }}" placeholder="8px 12px" class="admin-input"></label>
        </div>
    </details>
</div>
