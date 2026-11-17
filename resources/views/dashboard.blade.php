@php
    try {
        $settings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $settings = [];
    }

    $cards = [
        [
            'key' => 'core.nav.settings',
            'label' => __('core.nav.settings'),
            'desc_key' => 'core.dashboard.desc.settings',
            'description' => __('core.dashboard.desc.settings'),
            'route' => 'admin.settings.index',
            'permission' => 'settings.manage',
        ],
        [
            'key' => 'core.nav.media',
            'label' => __('core.nav.media'),
            'desc_key' => 'core.dashboard.desc.media',
            'description' => __('core.dashboard.desc.media'),
            'route' => 'admin.media.index',
            'permission' => 'media.manage',
        ],
        [
            'key' => 'core.nav.pages',
            'label' => __('core.nav.pages'),
            'desc_key' => 'core.dashboard.desc.pages',
            'description' => __('core.dashboard.desc.pages'),
            'route' => 'admin.pages.index',
            'permission' => 'pages.manage',
        ],
        [
            'key' => 'core.nav.themes',
            'label' => __('core.nav.themes'),
            'desc_key' => 'core.dashboard.desc.themes',
            'description' => __('core.dashboard.desc.themes'),
            'route' => 'admin.themes.index',
            'permission' => 'themes.manage',
        ],
        [
            'key' => 'core.nav.plugins',
            'label' => __('core.nav.plugins'),
            'desc_key' => 'core.dashboard.desc.plugins',
            'description' => __('core.dashboard.desc.plugins'),
            'route' => 'admin.plugins.index',
            'permission' => 'plugins.view',
        ],
        [
            'key' => 'core.nav.install_plugins',
            'label' => __('core.nav.install_plugins'),
            'desc_key' => 'core.dashboard.desc.install_plugins',
            'description' => __('core.dashboard.desc.install_plugins'),
            'route' => 'admin.plugins.create',
            'permission' => 'plugins.install',
        ],
        [
            'key' => 'core.nav.users',
            'label' => __('core.nav.users'),
            'desc_key' => 'core.dashboard.desc.users',
            'description' => __('core.dashboard.desc.users'),
            'route' => 'admin.users.index',
            'permission' => 'users.manage',
        ],
        [
            'key' => 'core.nav.roles',
            'label' => __('core.nav.roles'),
            'desc_key' => 'core.dashboard.desc.roles',
            'description' => __('core.dashboard.desc.roles'),
            'route' => 'admin.roles.index',
            'permission' => 'roles.manage',
        ],
        [
            'key' => 'core.nav.permissions',
            'label' => __('core.nav.permissions'),
            'desc_key' => 'core.dashboard.desc.permissions',
            'description' => __('core.dashboard.desc.permissions'),
            'route' => 'admin.permissions.index',
            'permission' => 'permissions.manage',
        ],
        [
            'key' => 'core.nav.admin',
            'label' => __('core.nav.admin'),
            'desc_key' => 'core.dashboard.desc.admin',
            'description' => __('core.dashboard.desc.admin'),
            'route' => 'admin.platform-registry.index',
            'super_admin_only' => true,
        ],
    ];

    $routeAccess = app(\App\Platform\Core\Access\RouteAccessGate::class);
    $visibleCards = collect($cards)->filter(fn (array $card): bool =>
        \Illuminate\Support\Facades\Route::has($card['route'])
        && $routeAccess->allowsRouteName(auth()->user(), $card['route'])
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="ainpa-page-title" data-i18n="core.dashboard.title">
            {{ __('core.dashboard.title') }}
        </h2>
    </x-slot>

    <div class="ainpa-dashboard-page">
        <div class="ainpa-page-container">
            @if (session('status'))
                <div class="ainpa-alert ainpa-alert-warning">
                    {{ session('status') }}
                </div>
            @endif

            <div class="ainpa-dashboard-hero">
                <h3 class="ainpa-section-title" data-i18n="core.dashboard.tools">{{ __('core.dashboard.tools') }}</h3>
                <p class="ainpa-section-description" data-i18n="core.dashboard.tools_hint">
                    {{ __('core.dashboard.tools_hint') }}
                </p>
            </div>

            @if ($visibleCards->isEmpty())
                <div class="ainpa-empty-state" data-i18n="core.dashboard.empty">
                    {{ __('core.dashboard.empty') }}
                </div>
            @else
                <div class="ainpa-tool-grid">
                    @foreach ($visibleCards as $card)
                        <a href="{{ route($card['route']) }}" class="ainpa-tool-card">
                            <h3 class="ainpa-tool-card-title" data-i18n="{{ $card['key'] }}">{{ $card['label'] }}</h3>
                            <p class="ainpa-tool-card-description" data-i18n="{{ $card['desc_key'] }}">{{ $card['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
