@php
    $user = Auth::user();
    $routeAccess = app(\App\Platform\Core\Access\RouteAccessGate::class);
    $hasAdminAccess = $routeAccess->allowsRouteName($user, 'dashboard');

    try {
        $platformSettings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $platformSettings = [];
    }

    $isArabicLanguage = false;
    $translations = [
        'Dashboard' => 'لوحة التحكم',
        'Admin' => 'الإدارة',
        'Platform Registry' => 'سجل المنصة',
        'Documentation' => 'التوثيق',
        'Menus' => 'القوائم',
        'Front Builder' => 'منشئ الواجهة',
        'Docs' => 'الوثائق',
        'Media' => 'الوسائط',
        'Pages' => 'الصفحات',
        'Themes' => 'القوالب',
        'Settings' => 'الإعدادات',
        'Backup' => 'النسخ الاحتياطي',
        'Plugins' => 'الإضافات',
        'Install Plugin' => 'تثبيت إضافة',
        'Theme Manager' => 'إدارة الثيمات',
        'Theme Builder' => 'منشئ الثيم',
        'Theme Editor' => 'محرر الثيم',
        'Users' => 'المستخدمون',
        'Roles' => 'الأدوار',
        'Permissions' => 'الصلاحيات',
        'Blog' => 'المدونة',
        'All Posts' => 'كل المقالات',
        'Add New Post' => 'إضافة مقال',
        'Categories' => 'التصنيفات',
        'Add Category' => 'إضافة تصنيف',
        'Blog Settings' => 'إعدادات المدونة',
        'AI Core' => 'نواة الذكاء',
        'AI Assistant' => 'مساعد الذكاء',
        'Professional Programmer' => 'المبرمج المحترف',
        'Professional Programmer Alerts' => 'تنبيهات المبرمج المحترف',
        'Overview' => 'نظرة عامة',
        'Content Management' => 'إدارة المحتوى',
        'Platform' => 'المنصة',
        'AI Tools' => 'أدوات الذكاء',
        'Users & Access' => 'المستخدمون والصلاحيات',
        'System' => 'النظام',
        'Home' => 'الرئيسية',
        'My Account' => 'حسابي',
        'Profile' => 'الملف الشخصي',
        'Log Out' => 'تسجيل الخروج',
        'Menu' => 'القائمة',
        'Close' => 'إغلاق',
    ];
    $t = fn (string $text): string => $isArabicLanguage ? ($translations[$text] ?? $text) : $text;

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
                $mapStoredAdminItem = function (array $item) use ($t, &$mapStoredAdminItem): ?array {
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
                    $label = (string) ($item['label'] ?: $item['title']);

                    return [
                        'label' => $t($label),
                        'href' => $href,
                        'active' => $directActive || $childActive,
                        'icon' => (string) ($item['icon'] ?: strtoupper(substr((string) $item['title'], 0, 1))),
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
            $menuItems = [
                [
                    'label' => $t('Home'),
                    'href' => route('front.home'),
                    'active' => request()->routeIs('front.home'),
                    'icon' => 'H',
                    'visible' => true,
                    'type' => 'link',
                    'children' => [],
                ],
                [
                    'label' => $t('My Account'),
                    'href' => route('front.account'),
                    'active' => request()->routeIs('front.account'),
                    'icon' => 'A',
                    'visible' => true,
                    'type' => 'link',
                    'children' => [],
                ],
            ];
    }

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
                {{ $t('Home') }}
            </a>

            <button
                type="button"
                class="z4-builder-sidebar-toggle"
                title="Toggle admin sidebar"
                aria-label="Toggle admin sidebar"
                @click="
                    const body = document.body;
                    const expanded = body.classList.toggle('page-builder-sidebar-expanded');
                    body.classList.toggle('page-builder-sidebar-compact', ! expanded);
                    try { window.localStorage.setItem('z4-page-builder-sidebar', expanded ? 'expanded' : 'compact'); } catch (error) {}
                "
            >{{ $t('Menu') }}</button>
        </div>

        <div class="z4-admin-bar-actions">
            <a href="{{ route('profile.edit') }}" class="z4-admin-profile-link">{{ $user->name }}</a>
            <form method="POST" action="{{ route('logout') }}" class="z4-admin-logout-form">
                @csrf
                <button type="submit" class="z4-admin-bar-button">{{ $t('Log Out') }}</button>
            </form>
            <button type="button" @click="mobileOpen = ! mobileOpen" class="z4-mobile-menu-button">
                {{ $t('Menu') }}
            </button>
        </div>
    </div>

    <aside class="z4-admin-sidebar" aria-label="Admin navigation">
        <div class="z4-admin-sidebar-scroll" x-data="{ openSection: @js($activeAdminSectionKey) }">
            @foreach ($menuItems as $item)
                @continue(! ($item['visible'] ?? true))

                @php
                    $children = $item['children'] ?? [];
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
                                    const panelLeft = Math.max(4, Math.min(sectionRect.right + 2, window.innerWidth - panelWidth - 8));
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
                                    const panelLeft = Math.max(4, Math.min(groupRect.right + 2, window.innerWidth - panelWidth - 8));
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
                            <span class="z4-admin-icon">{{ $item['icon'] }}</span>
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
                                                    <span class="z4-admin-icon">{{ $child['icon'] }}</span>
                                                    <span class="z4-admin-link-label">{{ $child['label'] }}</span>
                                                </a>
                                            @else
                                                <span class="z4-admin-link z4-admin-parent-link {{ $child['active'] ? 'is-active' : '' }}">
                                                    <span class="z4-admin-icon">{{ $child['icon'] }}</span>
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
                                        <span class="z4-admin-icon">{{ $child['icon'] }}</span>
                                        <span class="z4-admin-link-label">{{ $child['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @else
                    <a href="{{ $item['href'] }}" class="z4-admin-link {{ $item['active'] ? 'is-active' : '' }}" @if($item['target'] ?? null) target="{{ $item['target'] }}" @endif>
                        <span class="z4-admin-icon">{{ $item['icon'] }}</span>
                        <span class="z4-admin-link-label">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>

    </aside>

    <div x-show="mobileOpen" x-cloak class="z4-mobile-overlay" @click="mobileOpen = false"></div>

    <aside x-show="mobileOpen" x-cloak class="z4-mobile-drawer" aria-label="Mobile admin navigation">
        <div class="z4-mobile-drawer-header">
            <x-application-logo class="z4-mobile-logo" />
            <button type="button" @click="mobileOpen = false" class="z4-mobile-close-button">{{ $t('Close') }}</button>
        </div>

        <div class="z4-mobile-drawer-body">
            @foreach ($menuItems as $item)
                @continue(! ($item['visible'] ?? true))

                @php
                    $children = $item['children'] ?? [];
                    $hasChildren = $children !== [];
                @endphp

                <div class="z4-mobile-section {{ $item['active'] ? 'is-active' : '' }}">
                    @if ($hasChildren)
                        <div class="z4-mobile-section-title">{{ $item['label'] }}</div>
                        @foreach ($children as $child)
                            @continue(! ($child['visible'] ?? true))
                            <a href="{{ $child['href'] }}" class="z4-mobile-link {{ $child['active'] ? 'is-active' : '' }}" @if($child['target'] ?? null) target="{{ $child['target'] }}" @endif>
                                <span class="z4-admin-icon">{{ $child['icon'] }}</span>
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
                            <span class="z4-admin-icon">{{ $item['icon'] }}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </aside>
</nav>
