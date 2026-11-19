<!DOCTYPE html>
@php
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
    $siteLanguage = $platformLocale ?? app()->getLocale();
    $htmlDirection = $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction($siteLanguage);
    $isArabicLanguage = str_starts_with(strtolower((string) $siteLanguage), 'ar');
    $languageSwitcherPath = trim(request()->path(), '/');
    $platformOnlyPaths = [
        'account', 'dashboard', 'profile', 'cart', 'checkout', 'student',
        'instructor', 'artist', 'my-courses', 'learn', 'certificates',
        'confirm-password', 'forgot-password', 'reset-password', 'login',
        'register', 'verify-email', 'email', 'store/account', 'store/cart',
        'store/checkout', 'store/orders', 'blog/writer',
    ];
    $hideTopLanguageSwitcher = false;

    foreach ($platformOnlyPaths as $platformPath) {
        if ($languageSwitcherPath === $platformPath || str_starts_with($languageSwitcherPath, $platformPath.'/')) {
            $hideTopLanguageSwitcher = true;
            break;
        }
    }

    $showTopLanguageSwitcher = count($platformLanguages ?? []) > 1 && ! $hideTopLanguageSwitcher;

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
@endphp
<html lang="{{ $siteLanguage }}" dir="{{ $htmlDirection }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $browserTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="keywords" content="{{ $seoKeywords }}">
        <meta name="robots" content="{{ $robots }}">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        @if ($ogImage)
            <meta property="og:image" content="{{ url($ogImage) }}">
        @endif
        @if ($siteIcon)
            <link rel="icon" href="{{ $siteIcon }}">
        @else
            <link rel="icon" href="data:,">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @if ($isArabicLanguage)
            <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />
            <style>
                html[lang^="ar"] body,
html[lang^="ar"] body :not(i):not(svg):not(code):not(pre):not(kbd):not(samp):not(text):not(.fa):not(.fas):not(.far):not(.fab):not(.material-icons):not(.material-symbols-outlined):not([class^="fa-"]):not([class*=" fa-"]):not(.font-mono):not([class*="font-mono"]):not(textarea[spellcheck="false"]) {
                    font-family: 'Cairo', 'Figtree', ui-sans-serif, system-ui, sans-serif !important;
                }
                }
            </style>
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if ($frontendStyleBundleUrl)
            <link rel="stylesheet" href="{{ $frontendStyleBundleUrl }}">
        @endif
        @include('platform.plugin-assets', ['scope' => 'frontend', 'kind' => 'styles'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-950">
        <div class="min-h-screen">
            {{-- The platform top bar / header menu owns the language switcher; the legacy top strip was removed. --}}

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
                                    {{ (string) ($frontendMenuItem['label'] ?: $frontendMenuItem['title']) }}
                                </a>
                            @endif
                        @endforeach
                        @auth
                            @if (auth()->user()->hasAnyRole(['super-admin', 'admin', 'staff', 'employee']) || auth()->user()->getAllPermissions()->isNotEmpty())
                                <a href="{{ route('dashboard') }}" class="text-slate-700 hover:text-slate-950">{{ __('core.nav.dashboard') }}</a>
                            @endif
                            @if (auth()->user()->hasRole('super-admin'))
                                <a href="{{ route('admin.platform-registry.index') }}" class="text-slate-700 hover:text-slate-950">{{ __('core.nav.admin') }}</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-md bg-slate-950 px-3 py-2 text-white hover:bg-slate-800">{{ __('core.common.log_out') }}</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="text-slate-700 hover:text-slate-950">{{ __('core.auth.sign_in') }}</a>
                            <a href="{{ route('register') }}" class="rounded-md bg-slate-950 px-3 py-2 text-white hover:bg-slate-800">{{ __('core.auth.register') }}</a>
                        @endauth
                    </nav>
                </div>
            </header>

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
        </div>
        @include('platform.plugin-assets', ['scope' => 'frontend', 'kind' => 'scripts'])

    
        {{-- Keep mixed-language text readable without flipping its alignment:
         block elements keep the page direction; only bidi runs are recomputed.
         app.js adds dir="auto" to blocks, so this repairs it after load and
         keeps repairing it when dynamic content appears. --}}
    <style>.platform-plaintext{unicode-bidi:plaintext;direction:inherit;text-align:inherit}[data-auto-direction="true"]:not(input):not(textarea){direction:inherit!important;unicode-bidi:plaintext;text-align:inherit}</style>
    <script>
        (function () {
            var blocks = {H1:1,H2:1,H3:1,H4:1,H5:1,H6:1,P:1,LI:1,DT:1,DD:1,DIV:1,BLOCKQUOTE:1,FIGCAPTION:1,SUMMARY:1,TD:1,TH:1};

            function fix(element) {
                if (element && blocks[element.tagName] && element.getAttribute('dir') === 'auto') {
                    element.removeAttribute('dir');
                    element.classList.add('platform-plaintext');
                }
            }

            function scan(root) {
                if (!root.querySelectorAll) { return; }
                root.querySelectorAll('[data-auto-direction="true"]').forEach(fix);
            }

            scan(document);

            new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.type === 'attributes') {
                        fix(mutation.target);
                        return;
                    }
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            fix(node);
                            scan(node);
                        }
                    });
                });
            }).observe(document.documentElement, {
                subtree: true,
                attributes: true,
                attributeFilter: ['dir'],
                childList: true,
            });

            window.addEventListener('load', function () { scan(document); });
        })();
    </script>
</body>
</html>


