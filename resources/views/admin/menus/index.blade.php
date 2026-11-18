<x-app-layout>
<style>
    /* ===== Admin sections: readable cards + editor ===== */
    .admin-menu-compact-sections {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)) !important;
        gap: 14px !important;
    }

    .admin-menu-compact-section {
        padding: 12px 14px 14px !important;
        border-radius: 14px !important;
        background: #fff !important;
        border: 1px solid #e4e9f0 !important;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04) !important;
    }

    .admin-menu-compact-section-link strong {
        font-size: 15px !important;
        color: #0f172a !important;
    }

    .admin-menu-compact-section-link small {
        font-size: 11.5px !important;
        color: #64748b !important;
    }

    /* the edited card takes the full row so fields have room */
    .admin-menu-compact-section.is-editing {
        grid-column: 1 / -1 !important;
    }

    .admin-menu-compact-section.is-editing .admin-menu-compact-editor-inline {
        width: 100% !important;
        max-width: none !important;
        margin-top: 10px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form {
        display: grid !important;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) 120px auto auto !important;
        gap: 12px 14px !important;
        align-items: end !important;
    }

    /* name field spans the full width */
    .admin-menu-compact-section .admin-menu-compact-form > .admin-field:has([data-i18n-field]) {
        grid-column: 1 / -1 !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .admin-field-label {
        font-size: 12.5px !important;
        font-weight: 700 !important;
        color: #475569 !important;
        margin-bottom: 4px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .admin-input {
        height: 42px !important;
        font-size: 13.5px !important;
        padding: 8px 12px !important;
        border-radius: 9px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-row {
        flex-wrap: nowrap !important;
        gap: 10px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-lang {
        flex: 0 0 150px !important;
        min-width: 150px !important;
        height: 42px !important;
        font-size: 13px !important;
        padding: 0 10px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-text {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        height: 42px !important;
        font-size: 13.5px !important;
        padding: 0 12px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-hint {
        font-size: 11px !important;
        color: #94a3b8 !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .z4-icon-preview {
        width: 42px !important;
        height: 42px !important;
    }

    .admin-menu-compact-section .admin-menu-compact-form .admin-menu-check-option {
        font-size: 13px !important;
        padding-bottom: 10px;
    }

    .admin-menu-compact-section .admin-menu-compact-form > button {
        height: 42px !important;
        padding: 0 22px !important;
        font-size: 13px !important;
        border-radius: 9px !important;
    }

    /* the create panel uses the same generous sizing */
    .admin-menu-compact-editor .admin-menu-compact-form .admin-input {
        height: 42px !important;
        font-size: 13.5px !important;
        padding: 8px 12px !important;
    }

    .admin-menu-compact-editor .admin-menu-compact-form .z4-i18n-text {
        height: 42px !important;
        font-size: 13.5px !important;
    }

    @media (max-width: 900px) {
        .admin-menu-compact-section .admin-menu-compact-form {
            grid-template-columns: 1fr 1fr !important;
        }
        .admin-menu-compact-section .admin-menu-compact-form > .admin-field:has([data-i18n-field]) {
            grid-column: 1 / -1 !important;
        }
        .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-row {
            flex-wrap: wrap !important;
        }
        .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-lang {
            flex: 0 0 130px !important;
            min-width: 130px !important;
        }
        .admin-menu-compact-section .admin-menu-compact-form .z4-i18n-text {
            flex: 1 1 170px !important;
            min-width: 150px !important;
        }
    }
</style>
<x-slot name="header">
        <h2 class="ainpa-page-title" data-i18n="core.menus.title">{{ __('core.menus.title') }}</h2>
    </x-slot>

    @php
        $locations = [
            'admin' => __('core.menus.tab_admin'),
            'frontend' => __('core.menus.tab_frontend'),
        ];

        $activeMenus = $menus->get($activeLocation, collect());
        $activeMenu = $activeMenuId ? $activeMenus->firstWhere('id', $activeMenuId) : null;
        $activeMenu = $activeMenu ?: $activeMenus->first();

        $orderedItems = collect();
        $parentChoices = collect();

        if ($activeMenu) {
            $itemsByParent = $activeMenu->items
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->groupBy(fn ($menuItem) => $menuItem->parent_id ?: 0);

            $walkItems = function ($parentId = 0, $depth = 0) use (&$walkItems, $itemsByParent) {
                return ($itemsByParent->get($parentId) ?: collect())
                    ->flatMap(function ($menuItem) use (&$walkItems, $depth) {
                        return collect([
                            ['item' => $menuItem, 'depth' => $depth],
                        ])->merge($walkItems($menuItem->id, $depth + 1));
                    });
            };

            $orderedItems = $walkItems();
            $parentChoices = $orderedItems;
        }
    @endphp

    <div class="ainpa-admin-page admin-menu-page is-admin-menu {{ $activeLocation === 'frontend' ? 'is-frontend-menu' : '' }}">
        <div class="ainpa-page-container admin-menu-container">
            @if (session('status'))
                <div class="ainpa-alert ainpa-alert-success">{{ session('status') }}</div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="ainpa-alert ainpa-alert-danger">{{ $errors->first() }}</div>
            @endif

            <nav class="admin-menu-tabs" aria-label="{{ __('core.menus.locations') }}">
                @foreach ($locations as $location => $label)
                    <a
                        href="{{ route('admin.menus.index', ['location' => $location]) }}"
                        class="admin-menu-tab {{ $activeLocation === $location ? 'is-active' : '' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <section class="admin-menu-card">
                <div class="admin-menu-card-header">
                    <div>
                        <h3 class="admin-menu-card-title">{{ $activeLocation === 'admin' ? __('core.menus.heading_admin_sections') : __('core.menus.heading_frontend_menus') }}</h3>
                        <p class="admin-menu-card-subtitle">
                            {{ $activeLocation === 'admin'
                                ? __('core.menus.hint_admin_sections')
                                : __('core.menus.hint_frontend_menus') }}
                        </p>
                    </div>

                    @if ($activeLocation === 'frontend')
                        <button
                            type="button"
                            class="ainpa-button ainpa-button-primary"
                            x-data
                            @click="$dispatch('open-menu-create')"
                         data-i18n="core.menus.new_menu">
                            {{ __('core.menus.new_menu') }}
                        </button>
                    @else
                        <button
                            type="button"
                            class="ainpa-button ainpa-button-primary ainpa-button-compact"
                            x-data
                            @click="$dispatch('open-admin-section-create')"
                         data-i18n="core.menus.new_section">
                            {{ __('core.menus.new_section') }}
                        </button>
                    @endif
                </div>

                @if ($activeLocation === 'frontend')
                    <div x-data="{ createOpen: false, editMenu: null }" @open-menu-create.window="createOpen = ! createOpen; editMenu = null">
                        <div x-show="createOpen" x-cloak class="admin-menu-panel">
                            <form method="POST" action="{{ route('admin.menus.store', 'frontend') }}" class="admin-menu-form">
                                @csrf
                                <div class="admin-menu-form-grid admin-menu-form-grid-six">
                                    <label class="admin-field admin-menu-span-two">
                                        <span class="admin-field-label" data-i18n="core.menus.name">{{ __('core.menus.name') }}</span>
                                        <input name="name" required placeholder="{{ __('core.menus.placeholder_main_menu') }}" class="admin-input">
                                    </label>
                                    <label class="admin-field admin-menu-span-two">
                                        <span class="admin-field-label">{{ __('core.menus.key') }}</span>
                                        <input name="key" placeholder="main" class="admin-input">
                                    </label>
                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.menus.icon">{{ __('core.menus.icon') }}</span>
                                        <div style="display:flex;align-items:center;gap:6px">
                                            <span class="z4-icon-preview" aria-hidden="true"></span>
                                            <input type="hidden" name="icon" value="{{ old('icon') }}" data-icon-input>
                                            <button type="button" class="ainpa-button ainpa-button-compact" data-icon-open>{{ __('core.common.choose') }}</button>
                                        </div>
                                    </label>
                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.menus.sort">{{ __('core.menus.sort') }}</span>
                                        <input type="number" name="sort_order" value="0" class="admin-input">
                                    </label>
                                    <label class="admin-field admin-menu-check-field">
                                        <span class="admin-menu-check-option">
                                            <input type="checkbox" name="is_active" value="1" checked>
                                            <span data-i18n="core.menus.active">{{ __('core.menus.active') }}</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="admin-menu-form-grid admin-menu-form-grid-action">
                                    <label class="admin-field">
                                        <span class="admin-field-label" data-i18n="core.menus.description">{{ __('core.menus.description') }}</span>
                                        <input name="description" class="admin-input">
                                    </label>
                                    <div class="admin-menu-actions">
                                        <button class="ainpa-button ainpa-button-primary" data-i18n="core.menus.create_menu">{{ __('core.menus.create_menu') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        @if ($activeMenus->isEmpty())
                            <div class="admin-menu-empty" data-i18n="core.menus.empty">{{ __('core.menus.empty') }}</div>
                        @else
                            <div class="admin-menu-source-grid">
                                @foreach ($activeMenus as $menu)
                                    <article class="admin-menu-source-card {{ $activeMenu?->id === $menu->id ? 'is-active' : '' }}">
                                        <div class="admin-menu-source-main">
                                            <div>
                                                <h4 class="admin-menu-source-title">{{ $menu->name }}</h4>
                                                <p class="admin-menu-source-meta" title="{{ $menu->key }}">{{ __('core.menus.items_count', ['count' => $menu->items->count()]) }} · {{ __('core.menus.sort', ['value' => $menu->sort_order]) }}</p>
                                            </div>
                                            <span class="ainpa-status-badge {{ $menu->is_active ? 'ainpa-status-active' : 'ainpa-status-inactive' }}">
                                                {{ $menu->is_active ? __('core.common.active') : __('core.common.inactive') }}
                                            </span>
                                        </div>

                                        <div class="admin-menu-source-actions">
                                            <a href="{{ route('admin.menus.index', ['location' => 'frontend', 'menu' => $menu->id]) }}" class="ainpa-button ainpa-button-compact" data-i18n="core.menus.manage_items">{{ __('core.menus.manage_items') }}</a>
                                            <button type="button" @click="editMenu = editMenu === {{ $menu->id }} ? null : {{ $menu->id }}; createOpen = false" class="ainpa-button ainpa-button-compact" data-i18n="core.menus.settings">{{ __('core.menus.settings') }}</button>
                                            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" onsubmit="return confirm(@js(__('core.menus.confirm.remove_menu_with_items')));">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ainpa-button ainpa-button-danger ainpa-button-compact" data-i18n="core.common.delete">{{ __('core.common.delete') }}</button>
                                            </form>
                                        </div>

                                        <div x-show="editMenu === {{ $menu->id }}" x-cloak class="admin-menu-card-edit">
                                            <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="admin-menu-form">
                                                @csrf
                                                @method('PATCH')
                                                <div class="admin-menu-form-grid admin-menu-form-grid-two">
                                                    <label class="admin-field">
                                                        <span class="admin-field-label" data-i18n="core.menus.name">{{ __('core.menus.name') }}</span>
                                                        <input name="name" value="{{ $menu->name }}" required class="admin-input">
                                                    </label>
                                                    <label class="admin-field">
                                                        <span class="admin-field-label">{{ __('core.menus.key') }}</span>
                                                        <input name="key" value="{{ preg_replace('/^platform\./', '', $menu->key) }}" class="admin-input">
                                                    </label>
                                                    <label class="admin-field">
                                                        <span class="admin-field-label" data-i18n="core.menus.icon">{{ __('core.menus.icon') }}</span>
                                                        <div style="display:flex;align-items:center;gap:6px">
                                                            <span class="z4-icon-preview" aria-hidden="true"></span>
                                                            <input type="hidden" name="icon" value="{{ old('icon', $menu->icon) }}" data-icon-input>
                                                            <button type="button" class="ainpa-button ainpa-button-compact" data-icon-open>{{ __('core.common.choose') }}</button>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="admin-menu-form-grid admin-menu-form-grid-settings">
                                                    <label class="admin-field">
                                                        <span class="admin-field-label" data-i18n="core.menus.sort">{{ __('core.menus.sort') }}</span>
                                                        <input type="number" name="sort_order" value="{{ $menu->sort_order }}" class="admin-input">
                                                    </label>
                                                    <label class="admin-field admin-menu-check-field">
                                                        <span class="admin-menu-check-option">
                                                            <input type="checkbox" name="is_active" value="1" @checked($menu->is_active)>
                                                            <span data-i18n="core.menus.active">{{ __('core.menus.active') }}</span>
                                                        </span>
                                                    </label>
                                                    <label class="admin-field admin-menu-description-field">
                                                        <span class="admin-field-label" data-i18n="core.menus.description">{{ __('core.menus.description') }}</span>
                                                        <input name="description" value="{{ $menu->description }}" class="admin-input">
                                                    </label>
                                                </div>
                                                <div class="admin-menu-actions">
                                                    <button class="ainpa-button" data-i18n="core.common.save">{{ __('core.common.save') }}</button>
                                                </div>
                                            </form>

                                            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" onsubmit="return confirm(@js(__('core.menus.confirm.remove_menu')));" class="admin-menu-danger-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ainpa-button ainpa-button-danger" data-i18n="core.menus.remove_menu">{{ __('core.menus.remove_menu') }}</button>
                                            </form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div x-data="{ createOpen: false, editMenu: null }" @open-admin-section-create.window="createOpen = ! createOpen; editMenu = null">
                        <div x-show="createOpen" x-cloak class="admin-menu-compact-editor">
                            <form method="POST" action="{{ route('admin.menus.store', 'admin') }}" class="admin-menu-compact-form">
                                @csrf
                                <div class="admin-field">
                <span class="admin-field-label" data-i18n="core.menus.section_name">{{ __('core.menus.section_name') }}</span>
                @include('admin.menus.partials.title-i18n', ['item' => null, 'baseField' => 'name', 'placeholder' => __('core.menus.new_admin_section')])
            </div>
                                <label class="admin-field">
                                    <span class="admin-field-label" data-i18n="core.menus.icon">{{ __('core.menus.icon') }}</span>
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <span class="z4-icon-preview" aria-hidden="true"></span>
                                        <input type="hidden" name="icon" value="{{ old('icon') }}" data-icon-input>
                                        <button type="button" class="ainpa-button ainpa-button-compact" data-icon-open>{{ __('core.common.choose') }}</button>
                                    </div>
                                </label>
                                <label class="admin-field admin-menu-sort-field">
                                    <span class="admin-field-label" data-i18n="core.menus.order">{{ __('core.menus.order') }}</span>
                                    <input type="number" name="sort_order" value="70" class="admin-input">
                                </label>
                                <input type="hidden" name="is_active" value="1">
                                <button class="ainpa-button ainpa-button-primary ainpa-button-compact" data-i18n="core.menus.create">{{ __('core.menus.create') }}</button>
                            </form>
                        </div>

                        @if ($activeMenus->isEmpty())
                            <div class="admin-menu-empty" data-i18n="core.menus.no_sections">{{ __('core.menus.no_sections') }}</div>
                        @else
                            <div class="admin-menu-compact-sections">
                                @foreach ($activeMenus as $menu)
                                    <article class="admin-menu-compact-section {{ $activeMenu?->id === $menu->id ? 'is-active' : '' }}" :class="{ 'is-editing': editMenu === {{ $menu->id }} }">
                                        <a href="{{ route('admin.menus.index', ['location' => 'admin', 'menu' => $menu->id]) }}" class="admin-menu-compact-section-link">
                                            <span class="admin-menu-compact-order">{{ $menu->sort_order }}</span>
                                            <span>
                                                <strong>{{ $menu->name }}</strong>
                                                <small>{{ __('core.menus.items_count', ['count' => $menu->items->count()]) }}</small>
                                            </span>
                                        </a>
                                        <div class="admin-menu-compact-actions">
                                            <button type="button" @click="editMenu = editMenu === {{ $menu->id }} ? null : {{ $menu->id }}; createOpen = false" class="ainpa-button ainpa-button-compact" data-i18n="core.menus.edit">{{ __('core.menus.edit') }}</button>
                                            @if ($menu->items->isEmpty())
                                                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" onsubmit="return confirm(@js(__('core.menus.confirm.delete_empty_section')));">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="ainpa-button ainpa-button-danger ainpa-button-compact" data-i18n="core.common.delete">{{ __('core.common.delete') }}</button>
                                                </form>
                                            @endif
                                        </div>

                                        <div x-show="editMenu === {{ $menu->id }}" x-cloak class="admin-menu-compact-editor admin-menu-compact-editor-inline">
                                            <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="admin-menu-compact-form">
                                                @csrf
                                                @method('PATCH')
                                                <div class="admin-field">
                                                    <span class="admin-field-label" data-i18n="core.menus.section_name">{{ __('core.menus.section_name') }}</span>
                                                    @include('admin.menus.partials.title-i18n', ['item' => $menu, 'baseField' => 'name', 'placeholder' => __('core.menus.section_name')])
                                                </div>
                                                <label class="admin-field">
                                                    <span class="admin-field-label" data-i18n="core.menus.icon">{{ __('core.menus.icon') }}</span>
                                                    <div style="display:flex;align-items:center;gap:6px">
                                                        <span class="z4-icon-preview" aria-hidden="true"></span>
                                                        <input type="hidden" name="icon" value="{{ old('icon', $menu->icon) }}" data-icon-input>
                                                        <button type="button" class="ainpa-button ainpa-button-compact" data-icon-open>{{ __('core.common.choose') }}</button>
                                                    </div>
                                                </label>
                                                <label class="admin-field admin-menu-sort-field">
                                                    <span class="admin-field-label" data-i18n="core.menus.order">{{ __('core.menus.order') }}</span>
                                                    <input type="number" name="sort_order" value="{{ $menu->sort_order }}" class="admin-input">
                                                </label>
                                                <label class="admin-menu-check-option admin-menu-compact-check">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" @checked($menu->is_active)>
                                                    <span data-i18n="core.menus.active">{{ __('core.menus.active') }}</span>
                                                </label>
                                                <button class="ainpa-button ainpa-button-compact" data-i18n="core.common.save">{{ __('core.common.save') }}</button>
                                            </form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            @if ($activeMenu)
                <section class="admin-menu-card" x-data="{ open: null, addOpen: false }">
                    <div class="admin-menu-card-header">
                        <div>
                            <h3 class="admin-menu-card-title">{{ $activeMenu->name }}</h3>
                            <p class="admin-menu-card-subtitle">
                                {{ __('core.menus.menu_items_count', ['count' => $activeMenu->items->count()]) }} · {{ __('core.menus.source_label', ['source' => \App\Platform\Core\Localization\Translation::label('core.menus.source.'.$activeMenu->source, ucfirst($activeMenu->source))]) }}
                                @if ($activeLocation === 'frontend')
                                    · <span title="data-platform-menu-key=&quot;{{ $activeMenu->key }}&quot;">{{ __('core.menus.vvveb_hook') }}</span>
                                @endif
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="addOpen = ! addOpen; open = null"
                            class="ainpa-button ainpa-button-primary"
                         data-i18n="core.menus.add_item">
                            {{ __('core.menus.add_item') }}
                        </button>
                    </div>

                    <div x-show="addOpen" x-cloak class="admin-menu-panel">
                        <form method="POST" action="{{ route('admin.menus.items.store-for-menu', $activeMenu) }}" class="admin-menu-form">
                            @csrf
                            @include($activeLocation === 'admin' ? 'admin.menus.partials.admin-item-fields' : 'admin.menus.partials.item-fields', [
                                'item' => null,
                                'activeLocation' => $activeLocation,
                                'permissions' => $permissions,
                                'routeNames' => $routeNames,
                                'parentChoices' => $parentChoices,
                            ])
                            <div class="admin-menu-actions">
                                <button class="ainpa-button ainpa-button-primary" data-i18n="core.menus.add_item">{{ __('core.menus.add_item') }}</button>
                            </div>
                        </form>
                    </div>

                    @if ($orderedItems->isEmpty())
                        <div class="admin-menu-empty" data-i18n="core.menus.no_items">{{ __('core.menus.no_items') }}</div>
                    @else
                        <div class="admin-menu-accordion">
                            @foreach ($orderedItems as $entry)
                                @php
                                    $item = $entry['item'];
                                    $depth = (int) $entry['depth'];
                                    $panelId = 'item-'.$item->id;
                                    $targetLabel = $item->route_name ?: ($item->url ?: __('core.menus.no_target'));
                                    $badgeLabel = $depth > 0 ? __('core.menus.badge_sub_item') : __('core.menus.badge_main_item');
                                @endphp

                                <article class="admin-menu-accordion-item {{ $depth > 0 ? 'is-child' : 'is-root' }}">
                                    <div class="admin-menu-accordion-row flex items-stretch gap-3">
                                        <button
                                            type="button"
                                            @click="open = open === '{{ $panelId }}' ? null : '{{ $panelId }}'; addOpen = false"
                                            class="admin-menu-accordion-button flex-1"
                                            :aria-expanded="open === '{{ $panelId }}'"
                                        >
                                            <span class="admin-menu-level admin-menu-level-{{ min($depth, 4) }}"></span>
                                            <span class="admin-menu-item-icon">
                                                @if (! empty($item->icon) && \App\Platform\Core\UI\IconSet::has((string) $item->icon))
                                                    {!! \App\Platform\Core\UI\IconSet::render($item->icon) !!}
                                                @else
                                                    <span class="z4-icon-empty" aria-hidden="true">—</span>
                                                @endif
                                            </span>
                                            <span class="admin-menu-item-main">
                                                <span class="admin-menu-item-title">{{ $item->title }}</span>
                                                <span class="admin-menu-item-target">{{ $targetLabel }}</span>
                                            </span>
                                            <span class="admin-menu-item-meta">
                                                <span class="admin-menu-badge">{{ $badgeLabel }}</span>
                                                <span class="admin-menu-badge">{{ $item->type }}</span>
                                                <span class="ainpa-status-badge {{ $item->is_active ? 'ainpa-status-active' : 'ainpa-status-inactive' }}">
                                                    {{ $item->is_active ? __('core.common.active') : __('core.common.inactive') }}
                                                </span>
                                                <span class="admin-menu-sort">{{ __('core.menus.sort', ['value' => $item->sort_order]) }}</span>
                                                <span class="admin-menu-toggle-text" x-show="open !== '{{ $panelId }}'" data-i18n="core.menus.open">{{ __('core.menus.open') }}</span>
                                                <span class="admin-menu-toggle-text" x-show="open === '{{ $panelId }}'" x-cloak data-i18n="core.common.close">{{ __('core.common.close') }}</span>
                                            </span>
                                        </button>

                                        <form method="POST" action="{{ route('admin.menus.items.destroy', $item) }}" onsubmit="return confirm(@js(__('core.menus.confirm.remove_item')));" class="admin-menu-row-delete-form flex items-center pr-3">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ainpa-button ainpa-button-danger ainpa-button-compact" data-i18n="core.common.delete">{{ __('core.common.delete') }}</button>
                                        </form>
                                    </div>

                                    <div x-show="open === '{{ $panelId }}'" x-cloak class="admin-menu-panel">
                                        <form method="POST" action="{{ route('admin.menus.items.update', $item) }}" class="admin-menu-form">
                                            @csrf
                                            @method('PATCH')
                                            @include($activeLocation === 'admin' ? 'admin.menus.partials.admin-item-fields' : 'admin.menus.partials.item-fields', [
                                                'item' => $item,
                                                'activeLocation' => $activeLocation,
                                                'permissions' => $permissions,
                                                'routeNames' => $routeNames,
                                                'parentChoices' => $parentChoices,
                                            ])
                                            <div class="admin-menu-actions">
                                                <button class="ainpa-button" data-i18n="core.menus.save_item">{{ __('core.menus.save_item') }}</button>
                                            </div>
                                        </form>

                                        <p class="admin-menu-inline-help" data-i18n="core.menus.item_delete_hint">{{ __('core.menus.item_delete_hint') }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

        </div>
    </div>

    @include('admin.menus.partials.icon-picker')
</x-app-layout>
