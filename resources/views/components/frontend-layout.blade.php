<!DOCTYPE html>
@php
    try {
        $pageBuilderRouteReady = \Illuminate\Support\Facades\Route::has('pages.show');
        $pageBuilderMenuPages = $pageBuilderRouteReady
            && \Illuminate\Support\Facades\Schema::hasTable('platform_pages')
            && \Illuminate\Support\Facades\Schema::hasColumn('platform_pages', 'show_in_menu')
            ? \Illuminate\Support\Facades\DB::table('platform_pages')
                ->where('content_type', 'page')
                ->where('status', 'published')
                ->where('show_in_menu', true)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
            : collect();
    } catch (\Throwable $exception) {
        $pageBuilderMenuPages = collect();
    }

    $pageBuilderMenu = $pageBuilderMenuPages->groupBy('parent_id');

    try {
        $platformSettings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $platformSettings = [];
    }

    $siteTitle = $platformSettings['general.site_title'] ?? config('app.name', 'Z4Rank Store');
    $tagline = $platformSettings['general.tagline'] ?? '';
    $browserTitle = $tagline !== '' ? $siteTitle.' - '.$tagline : $siteTitle;
    $seoTitle = $platformSettings['seo.seo_title'] ?? $siteTitle;
    $seoDescription = $platformSettings['seo.seo_description'] ?? $tagline;
    $seoKeywords = $platformSettings['seo.seo_keywords'] ?? '';
    $robots = (($platformSettings['seo.robots_index'] ?? true) ? 'index' : 'noindex').','.(($platformSettings['seo.robots_follow'] ?? true) ? 'follow' : 'nofollow');
    $ogTitle = $platformSettings['seo.open_graph_title'] ?? $seoTitle;
    $ogDescription = $platformSettings['seo.open_graph_description'] ?? $seoDescription;
    $ogImage = $platformSettings['seo.open_graph_image'] ?? null;
    $siteIcon = $platformSettings['general.site_icon'] ?? null;
    $siteLogo = $platformSettings['general.site_logo'] ?? null;
    $siteLanguage = ($platformSettings['general.site_language'] ?? 'ar') === 'en' ? 'en' : 'ar';
    $isArabicLanguage = $siteLanguage === 'ar';
    $htmlDirection = $isArabicLanguage ? 'rtl' : 'ltr';
    $translations = [
        'Home' => 'الرئيسية',
        'My Account' => 'حسابي',
        'Dashboard' => 'لوحة التحكم',
        'Admin' => 'الإدارة',
        'Log Out' => 'تسجيل الخروج',
        'Log In' => 'تسجيل الدخول',
        'Register' => 'إنشاء حساب',
    ];
    $t = fn (string $text): string => $isArabicLanguage ? ($translations[$text] ?? $text) : $text;

    try {
        $frontendMenuItems = app(\App\Platform\Core\Menus\MenuManager::class)->getFrontendMenu(auth()->user());
    } catch (\Throwable $exception) {
        $frontendMenuItems = [];
    }

    if ($frontendMenuItems === []) {
        $frontendMenuItems = [[
            'id' => 'fallback-home',
            'title' => 'Home',
            'label' => 'Home',
            'route_name' => 'front.home',
            'route_params' => [],
            'target' => '_self',
            'metadata' => [],
        ]];
    }

    $frontendMenuRouteNames = collect($frontendMenuItems)
        ->pluck('route_name')
        ->filter()
        ->values()
        ->all();

    $frontendMenuHref = function (array $item): ?string {
        $routeName = $item['route_name'] ?? null;
        $url = $item['url'] ?? null;

        if (is_string($routeName) && \Illuminate\Support\Facades\Route::has($routeName)) {
            return route($routeName, $item['route_params'] ?? []);
        }

        if (is_string($url) && trim($url) !== '') {
            return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
                ? $url
                : url($url);
        }

        return null;
    };

    $frontendMenuClass = function (array $item): string {
        $style = data_get($item, 'metadata.style', []);
        $cssClass = is_array($style) ? (string) ($style['css_class'] ?? '') : '';
        $cssClass = trim(preg_replace('/[^A-Za-z0-9_:\-\s\/\[\]\.]/', '', $cssClass) ?? '');

        return trim('text-slate-700 hover:text-slate-950 '.$cssClass);
    };

    $frontendStyleBundle = null;
    $frontendStyleBundleUrl = null;
    if (class_exists(\App\Platform\Core\Theme\FrontendStyleBundle::class)) {
        try {
            $frontendStyleBundle = app(\App\Platform\Core\Theme\FrontendStyleBundle::class);
            $frontendStyleBundleUrl = $frontendStyleBundle->url($frontendMenuItems);
        } catch (\Throwable $exception) {
            report($exception);
            $frontendStyleBundle = null;
            $frontendStyleBundleUrl = null;
        }
    }

    $contentRenderer = app(\App\Platform\Core\Rendering\PlatformContentRenderer::class);
    $dynamicHeaders = collect();
    $dynamicFooters = collect();
    $dynamicLayoutCss = '';

    $usesVvvebLayout = class_exists(\Modules\PageBuilder\ThemeCompositionService::class);

    try {
        if ($usesVvvebLayout) {
            $vvvebLayout = app(\Modules\PageBuilder\ThemeCompositionService::class)->layoutViewData();
            $dynamicHeaders = $vvvebLayout['dynamicHeaders'];
            $dynamicFooters = $vvvebLayout['dynamicFooters'];
            $dynamicLayoutCss = $vvvebLayout['dynamicLayoutCss'];
        } else {
            $dynamicHeaders = $contentRenderer->publishedLayoutSections('header');
            $dynamicFooters = $contentRenderer->publishedLayoutSections('footer');
            $dynamicLayoutCss = $contentRenderer->layoutCss();
        }
    } catch (\Throwable $exception) {
        $dynamicHeaders = collect();
        $dynamicFooters = collect();
        $dynamicLayoutCss = '';
    }

@endphp
<html lang="{{ $siteLanguage }}" dir="{{ $htmlDirection }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @unless (isset($head))
            <title>{{ $browserTitle }}</title>
            <meta name="description" content="{{ $seoDescription }}">
            <meta name="keywords" content="{{ $seoKeywords }}">
            <meta name="robots" content="{{ $robots }}">
            <meta property="og:title" content="{{ $ogTitle }}">
            <meta property="og:description" content="{{ $ogDescription }}">
            @if ($ogImage)
                <meta property="og:image" content="{{ url($ogImage) }}">
            @endif
        @endunless
        @if ($siteIcon)
            <link rel="icon" href="{{ $siteIcon }}">
        @else
            <link rel="icon" href="data:,">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if ($frontendStyleBundleUrl)
            <link rel="stylesheet" href="{{ $frontendStyleBundleUrl }}">
        @endif
        @include('platform.plugin-assets', ['scope' => 'frontend', 'kind' => 'styles'])
        @if ($usesVvvebLayout)
            <link rel="stylesheet" href="{{ url('/page-builder-assets/v3/demo/landing/css/style.bundle.css') }}">
            <link rel="stylesheet" href="{{ url('/page-builder-assets/v3/demo/landing/css/custom.css') }}">
        @endif
        @if ($dynamicLayoutCss !== '')
            <style data-platform-layout-css>{!! $dynamicLayoutCss !!}</style>
        @endif
        @isset($head)
            {{ $head }}
        @endisset
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-950">
        <div class="min-h-screen">
            @if ($dynamicHeaders->isNotEmpty())
                @foreach ($dynamicHeaders as $dynamicHeader)
                    <div data-platform-content-type="header" data-platform-content-id="{{ $dynamicHeader->id }}">
                        {!! $dynamicHeader->rendered_html !!}
                    </div>
                @endforeach
            @elseif (! $usesVvvebLayout)
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <a href="{{ route('front.home') }}" class="flex items-center text-lg font-bold tracking-tight text-slate-950">
                            @if ($siteLogo)
                                <img src="{{ $siteLogo }}" alt="{{ $siteTitle }}" class="h-10 w-auto object-contain">
                            @else
                                {{ $siteTitle }}
                            @endif
                        </a>

                        <nav class="flex items-center gap-3 text-sm font-medium">
                            @foreach ($frontendMenuItems as $frontendMenuItem)
                                @php
                                    $href = $frontendMenuHref($frontendMenuItem);
                                    $target = $frontendMenuItem['target'] ?? '_self';
                                @endphp
                                @if ($href)
                                    <a
                                        href="{{ $href }}"
                                    class="{{ $frontendStyleBundle ? $frontendStyleBundle->menuItemClass($frontendMenuItem) : '' }} {{ $frontendMenuClass($frontendMenuItem) }}"
                                        @if ($target === '_blank') target="_blank" rel="noopener" @endif
                                    >
                                        {{ $t((string) ($frontendMenuItem['label'] ?: $frontendMenuItem['title'])) }}
                                    </a>
                                @endif
                            @endforeach
                            @foreach ($pageBuilderMenu->get('', collect()) as $menuPage)
                                @php $children = $pageBuilderMenu->get($menuPage->id, collect()); @endphp
                                @if ($children->isNotEmpty())
                                    <div class="relative group">
                                        <a href="{{ route('pages.show', $menuPage->slug) }}" class="text-slate-700 hover:text-slate-950">
                                            {{ $menuPage->menu_label ?: $menuPage->title }}
                                        </a>
                                        <div class="absolute left-0 top-full z-20 hidden min-w-48 border border-slate-200 bg-white py-2 shadow-lg group-hover:block">
                                            @foreach ($children as $childPage)
                                                <a href="{{ route('pages.show', $childPage->slug) }}" class="block px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-slate-950">
                                                    {{ $childPage->menu_label ?: $childPage->title }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ route('pages.show', $menuPage->slug) }}" class="text-slate-700 hover:text-slate-950">
                                        {{ $menuPage->menu_label ?: $menuPage->title }}
                                    </a>
                                @endif
                            @endforeach
                            @auth
                                @if (! in_array('front.account', $frontendMenuRouteNames, true))
                                    <a href="{{ route('front.account') }}" class="text-slate-700 hover:text-slate-950">{{ $t('My Account') }}</a>
                                @endif
                                @if (auth()->user()->hasAnyRole(['super-admin', 'admin', 'staff', 'employee']) || auth()->user()->getAllPermissions()->isNotEmpty())
                                    <a href="{{ route('dashboard') }}" class="text-slate-700 hover:text-slate-950">{{ $t('Dashboard') }}</a>
                                @endif
                                @if (auth()->user()->hasRole('super-admin'))
                                    <a href="{{ route('admin.platform-registry.index') }}" class="text-slate-700 hover:text-slate-950">{{ $t('Admin') }}</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-slate-950 px-3 py-2 text-white hover:bg-slate-800">{{ $t('Log Out') }}</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="text-slate-700 hover:text-slate-950">{{ $t('Log In') }}</a>
                                <a href="{{ route('register') }}" class="rounded-md bg-slate-950 px-3 py-2 text-white hover:bg-slate-800">{{ $t('Register') }}</a>
                            @endauth
                        </nav>
                    </div>
                </header>
            @endif

            <main>
                @if (session('status'))
                    <div class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>

            @foreach ($dynamicFooters as $dynamicFooter)
                <div data-platform-content-type="footer" data-platform-content-id="{{ $dynamicFooter->id }}">
                    {!! $dynamicFooter->rendered_html !!}
                </div>
            @endforeach
        </div>
        @include('platform.plugin-assets', ['scope' => 'frontend', 'kind' => 'scripts'])
    </body>
</html>
