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
        <span class="admin-field-label" data-i18n="core.menus.display_name">{{ __('core.menus.display_name') }}</span>
        <input name="title" value="{{ old('title', $item->title ?? '') }}" required class="admin-input">
    </label>

    <label class="admin-field frontend-menu-label-field">
        <span class="admin-field-label" data-i18n="core.menus.menu_label">{{ __('core.menus.menu_label') }}</span>
        <input name="label" value="{{ old('label', $item->label ?? '') }}" class="admin-input">
    </label>

    <label class="admin-field frontend-menu-parent-field">
        <span class="admin-field-label" data-i18n="core.menus.parent_submenu">{{ __('core.menus.parent_submenu') }}</span>
        <select name="parent_id" class="admin-input">
            <option value="" data-i18n="core.menus.main_item">{{ __('core.menus.main_item') }}</option>
            @foreach ($parentChoices as $choice)
                @continue($item && (int) $choice['item']->id === (int) $item->id)
                <option value="{{ $choice['item']->id }}" @selected((int) old('parent_id', $item->parent_id ?? 0) === (int) $choice['item']->id)>
                    {{ str_repeat('— ', (int) $choice['depth']) }}{{ $choice['item']->title }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="admin-field frontend-menu-type-field">
        <span class="admin-field-label" data-i18n="core.menus.type">{{ __('core.menus.type') }}</span>
        <select name="type" x-model="itemType" class="admin-input">
            <option value="route" data-i18n="core.menus.route">{{ __('core.menus.route') }}</option>
            <option value="link" data-i18n="core.menus.link">{{ __('core.menus.link') }}</option>
            <option value="header" data-i18n="core.menus.parent_label">{{ __('core.menus.parent_label') }}</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-target-field">
        <span class="admin-field-label" data-i18n="core.menus.target">{{ __('core.menus.target') }}</span>
        <select name="target" class="admin-input">
            <option value="_self" @selected(old('target', $item->target ?? '_self') === '_self') data-i18n="core.menus.same_tab">{{ __('core.menus.same_tab') }}</option>
            <option value="_blank" @selected(old('target', $item->target ?? '_self') === '_blank') data-i18n="core.menus.new_tab">{{ __('core.menus.new_tab') }}</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-destination-field" x-show="itemType === 'route'">
        <span class="admin-field-label" data-i18n="core.menus.registered_route">{{ __('core.menus.registered_route') }}</span>
        <select name="route_name" class="admin-input">
            <option value="" data-i18n="core.menus.choose_route">{{ __('core.menus.choose_route') }}</option>
            @foreach ($routeNames as $routeName)
                <option value="{{ $routeName }}" @selected(old('route_name', $item->route_name ?? '') === $routeName)>{{ $routeName }}</option>
            @endforeach
        </select>
    </label>

    <label class="admin-field frontend-menu-destination-field" x-show="itemType === 'link'">
        <span class="admin-field-label">URL</span>
        <input name="url" value="{{ old('url', $item->url ?? '') }}" placeholder="/pages/example" class="admin-input">
    </label>

    <div class="admin-menu-note admin-menu-note-warning frontend-menu-destination-field" x-show="itemType === 'header'" data-i18n="core.menus.parent_label_hint">
        {{ __('core.menus.parent_label_hint') }}
    </div>

    <label class="admin-field frontend-menu-audience-field">
        <span class="admin-field-label" data-i18n="core.menus.audience">{{ __('core.menus.audience') }}</span>
        <select name="audience" class="admin-input">
            <option value="all" @selected($audience === 'all') data-i18n="core.menus.everyone">{{ __('core.menus.everyone') }}</option>
            <option value="guest" @selected($audience === 'guest') data-i18n="core.menus.guests">{{ __('core.menus.guests') }}</option>
            <option value="authenticated" @selected($audience === 'authenticated') data-i18n="core.menus.signed_in">{{ __('core.menus.signed_in') }}</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-action-field">
        <span class="admin-field-label" data-i18n="core.menus.interaction">{{ __('core.menus.interaction') }}</span>
        <select name="action_method" class="admin-input">
            <option value="get" @selected($actionMethod === 'get') data-i18n="core.menus.navigate">{{ __('core.menus.navigate') }}</option>
            <option value="post" @selected($actionMethod === 'post') data-i18n="core.menus.post_action">{{ __('core.menus.post_action') }}</option>
        </select>
    </label>

    <label class="admin-field frontend-menu-permission-field">
        <span class="admin-field-label" data-i18n="core.menus.permission">{{ __('core.menus.permission') }}</span>
        <select name="permission" class="admin-input">
            <option value="" data-i18n="core.menus.no_permission">{{ __('core.menus.no_permission') }}</option>
            @foreach ($permissions as $permission)
                <option value="{{ $permission }}" @selected(old('permission', $item->permission ?? '') === $permission)>{{ $permission }}</option>
            @endforeach
        </select>
    </label>

    <label class="admin-field frontend-menu-icon-field">
        <span class="admin-field-label" data-i18n="core.menus.icon">{{ __('core.menus.icon') }}</span>
        <div style="display:flex;align-items:center;gap:6px">
            <span class="z4-icon-preview" aria-hidden="true"></span>
            <input type="hidden" name="icon" value="{{ old('icon', $item->icon ?? '') }}" data-icon-input>
            <button type="button" class="ainpa-button ainpa-button-compact" data-icon-open>Choose</button>
        </div>
    </label>

    <label class="admin-field frontend-menu-sort-field">
        <span class="admin-field-label" data-i18n="core.menus.order">{{ __('core.menus.order') }}</span>
        <input name="sort_order" type="number" value="{{ old('sort_order', $item->sort_order ?? 0) }}" class="admin-input">
    </label>

    <label class="admin-menu-check-option admin-menu-compact-check frontend-menu-active-field">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked((bool) $isActive)>
        <span data-i18n="core.menus.active">{{ __('core.menus.active') }}</span>
    </label>

    <details class="frontend-menu-advanced">
        <summary data-i18n="core.menus.optional_styling">{{ __('core.menus.optional_styling') }}</summary>
        <div class="frontend-menu-style-grid">
            <label class="admin-field">
                <span class="admin-field-label" data-i18n="core.menus.css_classes">{{ __('core.menus.css_classes') }}</span>
                <input name="css_class" value="{{ old('css_class', $style['css_class'] ?? '') }}" class="admin-input">
            </label>
            <label class="admin-field">
                <span class="admin-field-label" data-i18n="core.menus.font_weight">{{ __('core.menus.font_weight') }}</span>
                <select name="font_weight" class="admin-input">
                    <option value="" data-i18n="core.menus.default">{{ __('core.menus.default') }}</option>
                    @foreach (['normal' => 'Normal', 'medium' => 'Medium', 'semibold' => 'Semibold', 'bold' => 'Bold'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('font_weight', $style['font_weight'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field"><span class="admin-field-label" data-i18n="core.menus.text">{{ __('core.menus.text') }}</span><input type="color" name="text_color" value="{{ old('text_color', $style['text_color'] ?? '#334155') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label" data-i18n="core.menus.background">{{ __('core.menus.background') }}</span><input type="color" name="background_color" value="{{ old('background_color', $style['background_color'] ?? '#ffffff') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label" data-i18n="core.menus.hover_text">{{ __('core.menus.hover_text') }}</span><input type="color" name="hover_text_color" value="{{ old('hover_text_color', $style['hover_text_color'] ?? '#0f172a') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label" data-i18n="core.menus.hover_background">{{ __('core.menus.hover_background') }}</span><input type="color" name="hover_background_color" value="{{ old('hover_background_color', $style['hover_background_color'] ?? '#f8fafc') }}" class="admin-color-input"></label>
            <label class="admin-field"><span class="admin-field-label" data-i18n="core.menus.border_radius">{{ __('core.menus.border_radius') }}</span><input name="border_radius" value="{{ old('border_radius', $style['border_radius'] ?? '') }}" placeholder="6px" class="admin-input"></label>
            <label class="admin-field"><span class="admin-field-label" data-i18n="core.menus.padding">{{ __('core.menus.padding') }}</span><input name="padding" value="{{ old('padding', $style['padding'] ?? '') }}" placeholder="8px 12px" class="admin-input"></label>
        </div>
    </details>
</div>
