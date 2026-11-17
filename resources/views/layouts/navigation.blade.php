@php
    $user = Auth::user();
    $routeAccess = app(\App\Platform\Core\Access\RouteAccessGate::class);
    $hasAdminAccess = $routeAccess->allowsRouteName($user, 'dashboard');

    try {
        $platformSettings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $platformSettings = [];
    }

    $menuItems = [];

    if ($hasAdminAccess) {
        try {
            $storedAdminItems = app(\App\Platform\Core\Menus\MenuManager::class)->getAdminMenu($user);
            $hasRegisteredAdminMenus = \Illuminate\Support\Facades\Schema::hasTable('menus')
                && \App\Platform\Core\Models\Menu::query()
                    ->where('location', 'admin')
                    ->where('is_active', true)
                    ->exists();

            if ($hasRegisteredAdminMenus && $storedAdminItems !== []) {
                $mapStoredAdminItem = function (array $item) use (&$mapStoredAdminItem): ?array {
                    $routeName = $item['route_name'] ?? null;
                    $url = $item['url'] ?? null;
                    $href = null;

                    if (is_string($routeName) && \Illuminate\Support\Facades\Route::has($routeName)) {
                        $href = route($routeName, $item['route_params'] ?? []);
                    } elseif (is_string($url) && trim($url) !== '') {
                        $href = str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
                            ? $url
                            : url($url);
                    }

                    $children = collect($item['children'] ?? [])
                        ->map($mapStoredAdminItem)
                        ->filter()
                        ->values()
                        ->all();

                    if ($href === null && $children === []) {
                        return null;
                    }

                    $directActive = false;

                    if (is_string($routeName)) {
                        $directActive = request()->routeIs($routeName) || request()->routeIs(rtrim($routeName, '.').'.*');
                    } elseif (is_string($url) && trim($url) !== '') {
                        $directActive = request()->is(trim((string) $url, '/').'*');
                    }

                    $childActive = collect($children)->contains(fn (array $child): bool => (bool) ($child['active'] ?? false));
                    $label = (string) ($item['title'] ?: $item['label']);

                    return [
                        'label' => $label,
                        'href' => $href,
                        'route_name' => $routeName,
                        'active' => $directActive || $childActive,
                        'icon' => (string) ($item['icon'] ?? ''),
                        'visible' => true,
                        'type' => (string) ($item['type'] ?? 'link'),
                        'target' => $item['target'] ?? null,
                        'children' => $children,
                    ];
                };

                $menuItems = collect($storedAdminItems)
                    ->map($mapStoredAdminItem)
                    ->filter()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $exception) {
            //
        }
    }

    if ($menuItems === [] && ! $hasAdminAccess) {
        $moduleItems = collect($moduleNavigation['items'] ?? [])
            ->filter(static fn (array $item): bool => (bool) ($item['visible'] ?? true))
            ->values()
            ->all();

        $menuItems = $moduleItems !== [] ? $moduleItems : [
                [
                    'label' => __('core.nav.home'),
                    'href' => route('front.home'),
                    'active' => request()->routeIs('front.home'),
                    'icon' => 'H',
                    'visible' => true,
                    'type' => 'link',
                    'children' => [],
                ],
                [
                    'label' => __('core.nav.my_account'),
                    'href' => route('front.account'),
                    'active' => request()->routeIs('front.account'),
                    'icon' => 'A',
                    'visible' => true,
                    'type' => 'link',
                    'children' => [],
                ],
        ];

        if ($moduleItems !== []) {
            $menuItems[] = [
                'label' => __('core.nav.my_account'),
                'href' => route('front.account'),
                'route_name' => 'front.account',
                'active' => request()->routeIs('front.account'),
                'icon' => 'A',
                'visible' => true,
                'type' => 'link',
                'children' => [],
            ];
        }
    }

    $findAccountItem = function (array $items) use (&$findAccountItem): ?array {
        foreach ($items as $item) {
            if (($item['route_name'] ?? null) === 'front.account' && ($item['visible'] ?? true)) {
                return $item;
            }

            $match = $findAccountItem($item['children'] ?? []);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    };
    $accountMenuItem = $findAccountItem($menuItems);

    $activeAdminSection = collect($menuItems)->first(fn (array $item): bool =>
        (bool) ($item['active'] ?? false)
        && (($item['type'] ?? null) === 'group' || ! empty($item['children'] ?? []))
    );
    $activeAdminSectionKey = $activeAdminSection
        ? 'admin-section-'.\Illuminate\Support\Str::slug($activeAdminSection['label'])
        : null;
@endphp

<nav x-data="{ mobileOpen: false }" class="z4-admin-nav">
    <div class="z4-admin-bar">
        <div class="z4-admin-bar-main">
            <a href="{{ route('front.home') }}" class="z4-admin-brand">
                <x-application-logo class="z4-admin-brand-logo" />
                <span>{{ $platformSettings['general.site_title'] ?? 'Z4Rank' }}</span>
            </a>

            <a href="{{ route('front.home') }}" class="z4-admin-home-link">
                {{ __('core.nav.home') }}
            </a>

            <button
                type="button"
                class="z4-builder-sidebar-toggle"
                title="{{ __('core.nav.toggle_sidebar') }}"
                aria-label="{{ __('core.nav.toggle_sidebar') }}"
                @click="
                    const body = document.body;
                    const expanded = body.classList.toggle('page-builder-sidebar-expanded');
                    body.classList.toggle('page-builder-sidebar-compact', ! expanded);
                    try { window.localStorage.setItem('z4-page-builder-sidebar', expanded ? 'expanded' : 'compact'); } catch (error) {}
                "
            >{{ __('core.common.menu') }}</button>
        </div>

        <div class="z4-admin-bar-actions">
            {{-- Language switcher: panel language is stored per user (users.locale). --}}
            @if (count($platformLanguages ?? []) > 1)
                <form
                    method="POST"
                    action="{{ route('front.account.language') }}"
                    class="z4-admin-lang-form"
                    style="display:flex;align-items:center;gap:6px;margin-inline-end:6px"
                >
                    @csrf
                    <label class="sr-only" for="z4-admin-panel-locale">Language</label>
                    <select
                        id="z4-admin-panel-locale"
                        name="locale"
                        onchange="this.form.submit()"
                        title="Language"
                        style="height:32px;border:1px solid #d0d5dd;border-radius:8px;background:#fff;color:#344054;font-size:13px;padding:0 8px"
                    >
                        @foreach ($platformLanguages as $code => $label)
                            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript>
                        <button type="submit" class="z4-admin-bar-button">OK</button>
                    </noscript>
                </form>
            @endif
            {{-- Translation shortcut moved to Settings. --}}

            @if ($accountMenuItem !== null && $accountMenuItem['href'])
                <a href="{{ $accountMenuItem['href'] }}" class="z4-admin-profile-link" @if($accountMenuItem['target'] ?? null) target="{{ $accountMenuItem['target'] }}" @endif>
                    @if (! empty($accountMenuItem['icon']))<span class="z4-admin-icon z4-admin-profile-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render($accountMenuItem['icon']) !!}</span>@endif
                    <span>{{ $accountMenuItem['label'] }}</span>
                </a>
            @else
                <span class="z4-admin-profile-link" aria-label="{{ $user->name }}">{{ $user->name }}</span>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="z4-admin-logout-form">
                @csrf
                <button type="submit" class="z4-admin-bar-button">{{ __('core.common.log_out') }}</button>
            </form>
            <button type="button" @click="mobileOpen = ! mobileOpen" class="z4-mobile-menu-button">
                {{ __('core.common.menu') }}
            </button>
        </div>
    </div>

    <aside class="z4-admin-sidebar" aria-label="{{ __('core.nav.admin_navigation') }}">
        <div class="z4-admin-sidebar-scroll" x-data="{ openSection: @js($activeAdminSectionKey) }">
            @foreach ($menuItems as $item)
                @continue(! ($item['visible'] ?? true))

                @php
                    $children = collect($item['children'] ?? [])->unique(fn (array $child): string => (string) ($child['href'] ?? '').'|'.(string) ($child['label'] ?? ''))->values()->all();
                    $hasChildren = $children !== [];
                    $isGroup = ($item['type'] ?? null) === 'group' || $hasChildren;
                    $sectionKey = 'admin-section-'.\Illuminate\Support\Str::slug($item['label']);
                    $activeSubmenu = collect($children)->first(fn (array $child): bool =>
                        (bool) ($child['active'] ?? false) && ! empty($child['children'] ?? [])
                    );
                    $activeSubmenuKey = $activeSubmenu
                        ? $sectionKey.'-'.\Illuminate\Support\Str::slug($activeSubmenu['label'])
                        : null;
                @endphp

                @if ($isGroup)
                    <section
                        class="z4-admin-section {{ $item['active'] ? 'is-active is-open' : '' }}"
                        x-data="{
                            openSubmenu: @js($activeSubmenuKey),
                            positionSectionFlyout(section) {
                                if (! window.matchMedia('(min-width: 769px)').matches) return;

                                const panel = section.querySelector(':scope > .z4-admin-section-body');
                                if (! panel) return;

                                requestAnimationFrame(() => {
                                    const sectionRect = section.getBoundingClientRect();
                                    const visiblePanelRect = panel.getBoundingClientRect();
                                    const panelHeight = Math.min(visiblePanelRect.height || panel.scrollHeight || 320, window.innerHeight - 58);
                                    const panelWidth = Math.min(Math.max(visiblePanelRect.width || panel.scrollWidth || 250, 250), 310);
                                    const rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
                                    const panelLeft = rtl
                                        ? Math.max(8, Math.min(sectionRect.left - panelWidth - 2, window.innerWidth - panelWidth - 8))
                                        : Math.max(4, Math.min(sectionRect.right + 2, window.innerWidth - panelWidth - 8));
                                    const panelTop = Math.max(42, Math.min(sectionRect.top, window.innerHeight - panelHeight - 12));

                                    panel.style.setProperty('left', panelLeft + 'px', 'important');
                                    panel.style.setProperty('top', panelTop + 'px', 'important');
                                });
                            },
                            positionSubmenuFlyout(group) {
                                if (! window.matchMedia('(min-width: 769px)').matches) return;

                                const panel = group.querySelector(':scope > .z4-admin-submenu');
                                if (! panel) return;

                                requestAnimationFrame(() => {
                                    const groupRect = group.getBoundingClientRect();
                                    const visiblePanelRect = panel.getBoundingClientRect();
                                    const panelHeight = Math.min(visiblePanelRect.height || panel.scrollHeight || 260, window.innerHeight - 58);
                                    const panelWidth = Math.min(Math.max(visiblePanelRect.width || panel.scrollWidth || 220, 220), 300);
                                    const rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
                                    const panelLeft = rtl
                                        ? Math.max(8, Math.min(groupRect.left - panelWidth - 2, window.innerWidth - panelWidth - 8))
                                        : Math.max(4, Math.min(groupRect.right + 2, window.innerWidth - panelWidth - 8));
                                    const panelTop = Math.max(42, Math.min(groupRect.top, window.innerHeight - panelHeight - 12));

                                    panel.style.setProperty('left', panelLeft + 'px', 'important');
                                    panel.style.setProperty('top', panelTop + 'px', 'important');
                                });
                            }
                        }"
                        :class="{ 'is-open': openSection === @js($sectionKey) }"
                        @mouseenter="if (openSection !== @js($sectionKey)) positionSectionFlyout($el)"
                        @focusin="if (openSection !== @js($sectionKey)) positionSectionFlyout($el)"
                    >
                        <button
                            type="button"
                            class="z4-admin-section-toggle"
                            @click="openSection = openSection === @js($sectionKey) ? null : @js($sectionKey)"
                            :aria-expanded="(openSection === @js($sectionKey)).toString()"
                            aria-controls="{{ $sectionKey }}"
                        >
                            @if (! empty($item['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($item['icon']) !!}</span>@endif
                            <span class="z4-admin-section-label">{{ $item['label'] }}</span>
                            <span class="z4-admin-chevron" aria-hidden="true"></span>
                        </button>

                        <div id="{{ $sectionKey }}" class="z4-admin-section-body" x-show="openSection === @js($sectionKey)">
                            @foreach ($children as $child)
                                @continue(! ($child['visible'] ?? true))

                                @php
                                    $grandChildren = $child['children'] ?? [];
                                    $hasGrandChildren = $grandChildren !== [];
                                    $childKey = $sectionKey.'-'.\Illuminate\Support\Str::slug($child['label']);
                                @endphp

                                @if ($hasGrandChildren)
                                    <div
                                        class="z4-admin-menu-group {{ $child['active'] ? 'is-active is-open' : '' }}"
                                        :class="{ 'is-open': openSubmenu === @js($childKey) }"
                                        @mouseenter="if (openSubmenu !== @js($childKey)) positionSubmenuFlyout($el)"
                                        @focusin="if (openSubmenu !== @js($childKey)) positionSubmenuFlyout($el)"
                                    >
                                        <div class="z4-admin-parent-row">
                                            @if ($child['href'])
                                                <a href="{{ $child['href'] }}" class="z4-admin-link z4-admin-parent-link {{ $child['active'] ? 'is-active' : '' }}" @if($child['target'] ?? null) target="{{ $child['target'] }}" @endif>
                                                    @if (! empty($child['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($child['icon']) !!}</span>@endif
                                                    <span class="z4-admin-link-label">{{ $child['label'] }}</span>
                                                </a>
                                            @else
                                                <span class="z4-admin-link z4-admin-parent-link {{ $child['active'] ? 'is-active' : '' }}">
                                                    @if (! empty($child['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($child['icon']) !!}</span>@endif
                                                    <span class="z4-admin-link-label">{{ $child['label'] }}</span>
                                                </span>
                                            @endif
                                            <button
                                                type="button"
                                                class="z4-admin-submenu-toggle"
                                                @click="openSubmenu = openSubmenu === @js($childKey) ? null : @js($childKey)"
                                                :aria-expanded="(openSubmenu === @js($childKey)).toString()"
                                                aria-controls="{{ $childKey }}"
                                            >
                                                <span class="z4-admin-chevron" aria-hidden="true"></span>
                                            </button>
                                        </div>

                                        <div id="{{ $childKey }}" class="z4-admin-submenu" x-show="openSubmenu === @js($childKey)">
                                            @foreach ($grandChildren as $grandChild)
                                                @continue(! ($grandChild['visible'] ?? true))
                                                <a href="{{ $grandChild['href'] }}" class="z4-admin-submenu-link {{ $grandChild['active'] ? 'is-active' : '' }}" @if($grandChild['target'] ?? null) target="{{ $grandChild['target'] }}" @endif>
                                                    {{ $grandChild['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ $child['href'] }}" class="z4-admin-link {{ $child['active'] ? 'is-active' : '' }}" @if($child['target'] ?? null) target="{{ $child['target'] }}" @endif>
                                        @if (! empty($child['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($child['icon']) !!}</span>@endif
                                        <span class="z4-admin-link-label">{{ $child['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @else
                    <a href="{{ $item['href'] }}" class="z4-admin-link {{ $item['active'] ? 'is-active' : '' }}" @if($item['target'] ?? null) target="{{ $item['target'] }}" @endif>
                        @if (! empty($item['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($item['icon']) !!}</span>@endif
                        <span class="z4-admin-link-label">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>

    </aside>

    <div x-show="mobileOpen" x-cloak class="z4-mobile-overlay" @click="mobileOpen = false"></div>

    <aside x-show="mobileOpen" x-cloak class="z4-mobile-drawer" aria-label="{{ __('core.nav.mobile_navigation') }}">
        <div class="z4-mobile-drawer-header">
            <x-application-logo class="z4-mobile-logo" />
            <button type="button" @click="mobileOpen = false" class="z4-mobile-close-button">{{ __('core.common.close') }}</button>
        </div>

        <div class="z4-mobile-drawer-body">
            @foreach ($menuItems as $item)
                @continue(! ($item['visible'] ?? true))

                @php
                    $children = collect($item['children'] ?? [])->unique(fn (array $child): string => (string) ($child['href'] ?? '').'|'.(string) ($child['label'] ?? ''))->values()->all();
                    $hasChildren = $children !== [];
                @endphp

                <div class="z4-mobile-section {{ $item['active'] ? 'is-active' : '' }}">
                    @if ($hasChildren)
                        <div class="z4-mobile-section-title">{{ $item['label'] }}</div>
                        @foreach ($children as $child)
                            @continue(! ($child['visible'] ?? true))
                            <a href="{{ $child['href'] }}" class="z4-mobile-link {{ $child['active'] ? 'is-active' : '' }}" @if($child['target'] ?? null) target="{{ $child['target'] }}" @endif>
                                @if (! empty($child['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($child['icon']) !!}</span>@endif
                                <span>{{ $child['label'] }}</span>
                            </a>
                            @foreach (($child['children'] ?? []) as $grandChild)
                                @continue(! ($grandChild['visible'] ?? true))
                                <a href="{{ $grandChild['href'] }}" class="z4-mobile-sublink {{ $grandChild['active'] ? 'is-active' : '' }}" @if($grandChild['target'] ?? null) target="{{ $grandChild['target'] }}" @endif>
                                    {{ $grandChild['label'] }}
                                </a>
                            @endforeach
                        @endforeach
                    @else
                        <a href="{{ $item['href'] }}" class="z4-mobile-link {{ $item['active'] ? 'is-active' : '' }}" @if($item['target'] ?? null) target="{{ $item['target'] }}" @endif>
                            @if (! empty($item['icon']))<span class="z4-admin-icon">{!! \App\Platform\Core\UI\IconSet::render($item['icon']) !!}</span>@endif
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </aside>
</nav>


