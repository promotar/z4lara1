<!DOCTYPE html>
@php
    try {
        $platformSettings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $platformSettings = [];
    }

    $siteTitle = $platformSettings['general.site_title'] ?? config('app.name', 'Laravel');
    $tagline = $platformSettings['general.tagline'] ?? '';
    $browserTitle = $tagline !== '' ? $siteTitle.' - '.$tagline : $siteTitle;
    $siteIcon = $platformSettings['general.site_icon'] ?? null;
    $siteIconPath = is_string($siteIcon) ? parse_url($siteIcon, PHP_URL_PATH) : null;
    $hasSiteIcon = is_string($siteIcon) && trim($siteIcon) !== '' && (
        str_starts_with($siteIcon, 'http://')
        || str_starts_with($siteIcon, 'https://')
        || (is_string($siteIconPath) && file_exists(public_path(ltrim($siteIconPath, '/'))))
    );

@endphp
<html lang="{{ str_replace('_', '-', $platformLocale ?? app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $browserTitle }}</title>
        @if ($hasSiteIcon)
            <link rel="icon" href="{{ $siteIcon }}">
        @else
            <link rel="icon" href="data:,">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @if (str_starts_with(strtolower((string) ($platformLocale ?? app()->getLocale())), 'ar'))
            <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />
            <style>
                html[lang^="ar"] body,
html[lang^="ar"] body :not(i):not(svg):not(code):not(pre):not(kbd):not(samp):not(text):not(.fa):not(.fas):not(.far):not(.fab):not(.material-icons):not(.material-symbols-outlined):not([class^="fa-"]):not([class*=" fa-"]):not(.font-mono):not([class*="font-mono"]):not(textarea[spellcheck="false"]) {
                    font-family: 'Cairo', 'Figtree', ui-sans-serif, system-ui, sans-serif !important;
                }
                }
            </style>
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('platform.plugin-assets', ['scope' => 'admin', 'kind' => 'styles'])
        @stack('styles')
        <style>
            /* Platform icon set (inline SVG) sizing inside the admin chrome. */
            .z4-admin-icon { display: inline-flex; align-items: center; justify-content: center; }
            .z4-admin-icon .z4-icon-svg { width: 16px; height: 16px; display: block; }
            .z4-admin-icon .z4-icon-text { font-size: 12px; font-weight: 800; line-height: 1; }
            .admin-menu-item-icon .z4-icon-svg { width: 16px; height: 16px; }
            .admin-menu-item-icon .z4-icon-empty { color: #98a2b3; font-size: 12px; line-height: 1; }
        </style>
        @if (str_starts_with(strtolower((string) ($platformDirection ?? 'ltr')), 'rtl'))
            {{-- RTL: mirror the fixed admin sidebar and its offsets (core default). --}}
            <style>
                [dir="rtl"] .z4-admin-sidebar,
                html[dir="rtl"] body nav.z4-admin-nav > aside.z4-admin-sidebar {
                    direction: rtl !important;
                    /* Logical offsets resolve against rtl: inline-start = right. */
                    inset-inline-start: 0 !important;
                    inset-inline-end: auto !important;
                    left: auto !important;
                    right: 0 !important;
                    margin-left: 0 !important;
                    margin-right: 0 !important;
                    border-right: 0 !important;
                    border-left: 1px solid var(--ainpa-admin-sidebar-line, var(--ainpa-admin-border, #e5e7eb)) !important;
                    box-shadow: -18px 0 42px rgba(1, 8, 20, 0.28) !important;
                }

                @media (min-width: 769px) {
                    [dir="rtl"] body .min-h-screen.bg-gray-100 {
                        padding-left: 0 !important;
                        padding-right: var(--ainpa-admin-sidebar-width, 224px) !important;
                    }
                }

                /* Flyout panels are positioned by JS (navigation.blade.php) so they open
                   to the left of the right-aligned sidebar; keep CSS offsets neutral. */
                [dir="rtl"] .z4-admin-sidebar .z4-admin-section:not(.is-open):hover > .z4-admin-section-body,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-menu-group:not(.is-open):hover > .z4-admin-submenu,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-section.is-open .z4-admin-menu-group:not(.is-open):hover > .z4-admin-submenu {
                    left: auto !important;
                    right: auto !important;
                    box-shadow: -22px 24px 54px rgba(80, 39, 22, 0.16) !important;
                }

                [dir="rtl"] .z4-admin-sidebar .z4-admin-section:not(.is-open):hover > .z4-admin-section-body::before {
                    left: auto !important;
                    right: -8px !important;
                    border-right: 0 !important;
                    border-left: 8px solid #ffffff !important;
                }

                [dir="rtl"] .z4-mobile-drawer {
                    left: auto !important;
                    right: 0 !important;
                }

                /* Keep the sidebar rows RTL so the icon sits on the right of the label. */
                [dir="rtl"] .z4-admin-sidebar .z4-admin-link,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-section-toggle,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-parent-row,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-submenu-link,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-section-label,
                [dir="rtl"] .z4-admin-sidebar .z4-admin-link-label,
                [dir="rtl"] .z4-admin-bar .z4-admin-profile-link {
                    direction: rtl !important;
                    text-align: right !important;
                }
            </style>
        @endif
    </head>
    <body class="font-sans antialiased">
        <div class="z4-admin-shell min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
        @include('platform.plugin-assets', ['scope' => 'admin', 'kind' => 'scripts'])
    
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
