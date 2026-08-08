<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title">Menu Settings</h2>
    </x-slot>

    @php
        $locations = [
            'admin' => 'Admin Menu',
            'frontend' => 'Frontend Menus',
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

            <nav class="admin-menu-tabs" aria-label="Menu locations">
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
                        <h3 class="admin-menu-card-title">{{ $activeLocation === 'admin' ? 'Admin menu sections' : 'Frontend menus' }}</h3>
                        <p class="admin-menu-card-subtitle">
                            {{ $activeLocation === 'admin'
                                ? 'Each section and item below is a registered part of the admin sidebar.'
                                : 'Frontend menus can be created, configured, and used by public layouts.' }}
                        </p>
                    </div>

                    @if ($activeLocation === 'frontend')
                        <button
                            type="button"
                            class="ainpa-button ainpa-button-primary"
                            x-data
                            @click="$dispatch('open-menu-create')"
                        >
                            New Menu
                        </button>
                    @else
                        <button
                            type="button"
                            class="ainpa-button ainpa-button-primary ainpa-button-compact"
                            x-data
                            @click="$dispatch('open-admin-section-create')"
                        >
                            New Section
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
                                        <span class="admin-field-label">Name</span>
                                        <input name="name" required placeholder="Main Menu" class="admin-input">
                                    </label>
                                    <label class="admin-field admin-menu-span-two">
                                        <span class="admin-field-label">Key</span>
                                        <input name="key" placeholder="main" class="admin-input">
                                    </label>
                                    <label class="admin-field">
                                        <span class="admin-field-label">Sort</span>
                                        <input type="number" name="sort_order" value="0" class="admin-input">
                                    </label>
                                    <label class="admin-field admin-menu-check-field">
                                        <span class="admin-menu-check-option">
                                            <input type="checkbox" name="is_active" value="1" checked>
                                            <span>Active</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="admin-menu-form-grid admin-menu-form-grid-action">
                                    <label class="admin-field">
                                        <span class="admin-field-label">Description</span>
                                        <input name="description" class="admin-input">
                                    </label>
                                    <div class="admin-menu-actions">
                                        <button class="ainpa-button ainpa-button-primary">Create Menu</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        @if ($activeMenus->isEmpty())
                            <div class="admin-menu-empty">No frontend menus yet.</div>
                        @else
                            <div class="admin-menu-source-grid">
                                @foreach ($activeMenus as $menu)
                                    <article class="admin-menu-source-card {{ $activeMenu?->id === $menu->id ? 'is-active' : '' }}">
                                        <div class="admin-menu-source-main">
                                            <div>
                                                <h4 class="admin-menu-source-title">{{ $menu->name }}</h4>
                                                <p class="admin-menu-source-meta">{{ $menu->key }} · {{ $menu->items->count() }} items · Sort {{ $menu->sort_order }}</p>
                                            </div>
                                            <span class="ainpa-status-badge {{ $menu->is_active ? 'ainpa-status-active' : 'ainpa-status-inactive' }}">
                                                {{ $menu->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>

                                        <div class="admin-menu-source-actions">
                                            <a href="{{ route('admin.menus.index', ['location' => 'frontend', 'menu' => $menu->id]) }}" class="ainpa-button ainpa-button-compact">Manage Items</a>
                                            <button type="button" @click="editMenu = editMenu === {{ $menu->id }} ? null : {{ $menu->id }}; createOpen = false" class="ainpa-button ainpa-button-compact">Settings</button>
                                            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" onsubmit="return confirm('Remove this frontend menu and all of its items?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ainpa-button ainpa-button-danger ainpa-button-compact">Delete</button>
                                            </form>
                                        </div>

                                        <div x-show="editMenu === {{ $menu->id }}" x-cloak class="admin-menu-card-edit">
                                            <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="admin-menu-form">
                                                @csrf
                                                @method('PATCH')
                                                <div class="admin-menu-form-grid admin-menu-form-grid-two">
                                                    <label class="admin-field">
                                                        <span class="admin-field-label">Name</span>
                                                        <input name="name" value="{{ $menu->name }}" required class="admin-input">
                                                    </label>
                                                    <label class="admin-field">
                                                        <span class="admin-field-label">Key</span>
                                                        <input name="key" value="{{ preg_replace('/^platform\./', '', $menu->key) }}" class="admin-input">
                                                    </label>
                                                </div>
                                                <div class="admin-menu-form-grid admin-menu-form-grid-settings">
                                                    <label class="admin-field">
                                                        <span class="admin-field-label">Sort</span>
                                                        <input type="number" name="sort_order" value="{{ $menu->sort_order }}" class="admin-input">
                                                    </label>
                                                    <label class="admin-field admin-menu-check-field">
                                                        <span class="admin-menu-check-option">
                                                            <input type="checkbox" name="is_active" value="1" @checked($menu->is_active)>
                                                            <span>Active</span>
                                                        </span>
                                                    </label>
                                                    <label class="admin-field admin-menu-description-field">
                                                        <span class="admin-field-label">Description</span>
                                                        <input name="description" value="{{ $menu->description }}" class="admin-input">
                                                    </label>
                                                </div>
                                                <div class="admin-menu-actions">
                                                    <button class="ainpa-button">Save</button>
                                                </div>
                                            </form>

                                            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" onsubmit="return confirm('Remove this frontend menu and its items?');" class="admin-menu-danger-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ainpa-button ainpa-button-danger">Remove Menu</button>
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
                                <label class="admin-field">
                                    <span class="admin-field-label">Section name</span>
                                    <input name="name" required placeholder="New admin section" class="admin-input">
                                </label>
                                <label class="admin-field admin-menu-sort-field">
                                    <span class="admin-field-label">Order</span>
                                    <input type="number" name="sort_order" value="70" class="admin-input">
                                </label>
                                <input type="hidden" name="is_active" value="1">
                                <button class="ainpa-button ainpa-button-primary ainpa-button-compact">Create</button>
                            </form>
                        </div>

                        @if ($activeMenus->isEmpty())
                            <div class="admin-menu-empty">No registered admin sections.</div>
                        @else
                            <div class="admin-menu-compact-sections">
                                @foreach ($activeMenus as $menu)
                                    <article class="admin-menu-compact-section {{ $activeMenu?->id === $menu->id ? 'is-active' : '' }}">
                                        <a href="{{ route('admin.menus.index', ['location' => 'admin', 'menu' => $menu->id]) }}" class="admin-menu-compact-section-link">
                                            <span class="admin-menu-compact-order">{{ $menu->sort_order }}</span>
                                            <span>
                                                <strong>{{ $menu->name }}</strong>
                                                <small>{{ $menu->items->count() }} items</small>
                                            </span>
                                        </a>
                                        <div class="admin-menu-compact-actions">
                                            <button type="button" @click="editMenu = editMenu === {{ $menu->id }} ? null : {{ $menu->id }}; createOpen = false" class="ainpa-button ainpa-button-compact">Edit</button>
                                            @if ($menu->items->isEmpty())
                                                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" onsubmit="return confirm('Delete this empty admin section?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="ainpa-button ainpa-button-danger ainpa-button-compact">Delete</button>
                                                </form>
                                            @endif
                                        </div>

                                        <div x-show="editMenu === {{ $menu->id }}" x-cloak class="admin-menu-compact-editor admin-menu-compact-editor-inline">
                                            <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="admin-menu-compact-form">
                                                @csrf
                                                @method('PATCH')
                                                <label class="admin-field">
                                                    <span class="admin-field-label">Section name</span>
                                                    <input name="name" value="{{ $menu->name }}" required class="admin-input">
                                                </label>
                                                <label class="admin-field admin-menu-sort-field">
                                                    <span class="admin-field-label">Order</span>
                                                    <input type="number" name="sort_order" value="{{ $menu->sort_order }}" class="admin-input">
                                                </label>
                                                <label class="admin-menu-check-option admin-menu-compact-check">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" @checked($menu->is_active)>
                                                    <span>Active</span>
                                                </label>
                                                <button class="ainpa-button ainpa-button-compact">Save</button>
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
                                {{ $activeMenu->items->count() }} menu items · {{ $activeMenu->key }} · {{ ucfirst($activeMenu->source) }} source
                                @if ($activeLocation === 'frontend')
                                    · VvvebJs hook <code>data-platform-menu-key="{{ $activeMenu->key }}"</code>
                                @endif
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="addOpen = ! addOpen; open = null"
                            class="ainpa-button ainpa-button-primary"
                        >
                            Add Item
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
                                <button class="ainpa-button ainpa-button-primary">Add Item</button>
                            </div>
                        </form>
                    </div>

                    @if ($orderedItems->isEmpty())
                        <div class="admin-menu-empty">No menu items yet.</div>
                    @else
                        <div class="admin-menu-accordion">
                            @foreach ($orderedItems as $entry)
                                @php
                                    $item = $entry['item'];
                                    $depth = (int) $entry['depth'];
                                    $panelId = 'item-'.$item->id;
                                    $targetLabel = $item->route_name ?: ($item->url ?: 'No target');
                                    $badgeLabel = $depth > 0 ? 'Sub item' : 'Main item';
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
                                            <span class="admin-menu-item-icon">{{ $item->icon ?: strtoupper(substr($item->title, 0, 1)) }}</span>
                                            <span class="admin-menu-item-main">
                                                <span class="admin-menu-item-title">{{ $item->title }}</span>
                                                <span class="admin-menu-item-target">{{ $targetLabel }}</span>
                                            </span>
                                            <span class="admin-menu-item-meta">
                                                <span class="admin-menu-badge">{{ $badgeLabel }}</span>
                                                <span class="admin-menu-badge">{{ $item->type }}</span>
                                                <span class="ainpa-status-badge {{ $item->is_active ? 'ainpa-status-active' : 'ainpa-status-inactive' }}">
                                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                <span class="admin-menu-sort">Sort {{ $item->sort_order }}</span>
                                                <span class="admin-menu-toggle-text" x-show="open !== '{{ $panelId }}'">Open</span>
                                                <span class="admin-menu-toggle-text" x-show="open === '{{ $panelId }}'" x-cloak>Close</span>
                                            </span>
                                        </button>

                                        <form method="POST" action="{{ route('admin.menus.items.destroy', $item) }}" onsubmit="return confirm('Remove this menu item?');" class="admin-menu-row-delete-form flex items-center pr-3">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ainpa-button ainpa-button-danger ainpa-button-compact">Delete</button>
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
                                                <button class="ainpa-button">Save Item</button>
                                            </div>
                                        </form>

                                        <p class="admin-menu-inline-help">Use the row Delete button to remove this menu item.</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

        </div>
    </div>
</x-app-layout>
