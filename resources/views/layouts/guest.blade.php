<!DOCTYPE html>
@php
    try {
        $platformSettings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
    } catch (\Throwable $exception) {
        $platformSettings = [];
    }

    $siteTitle = $platformSettings['general.site_title'] ?? config('app.name', 'Art INPA');
    $tagline = $platformSettings['general.tagline'] ?? 'International Network for Plastic Art';
    $browserTitle = $tagline !== '' ? $siteTitle.' - '.$tagline : $siteTitle;
    $siteIcon = $platformSettings['general.site_icon'] ?? null;
    $activeLocale = $platformLocale ?? app()->getLocale();
    $isArabic = str_starts_with(strtolower((string) $activeLocale), 'ar');
@endphp
<html lang="{{ str_replace('_', '-', $activeLocale) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $browserTitle }}</title>
        @if ($siteIcon)
            <link rel="icon" href="{{ $siteIcon }}">
        @else
            <link rel="icon" href="data:,">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @if ($isArabic)
            <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('auth-brand-overrides.css') }}?v=20260721-account-responsive-audit-3">
        @include('platform.plugin-assets', ['scope' => 'guest', 'kind' => 'styles'])

        @if ($isArabic)
            <style>
                html[lang^="ar"] body,
html[lang^="ar"] body :not(i):not(svg):not(code):not(pre):not(kbd):not(samp):not(text):not(.fa):not(.fas):not(.far):not(.fab):not(.material-icons):not(.material-symbols-outlined):not([class^="fa-"]):not([class*=" fa-"]):not(.font-mono):not([class*="font-mono"]):not(textarea[spellcheck="false"]) {
                    font-family: 'Cairo', 'Figtree', ui-sans-serif, system-ui, sans-serif !important;
                }
                }
                .ainpa-auth-locale-bar {
                    position: fixed;
                    top: 14px;
                    inset-inline-end: 18px;
                    z-index: 30;
                    display: inline-flex;
                    align-items: center;
                    padding: .30rem .55rem;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, .92);
                    color: #7f1d1d;
                    box-shadow: 0 8px 24px rgba(15, 23, 42, .10);
                }
            </style>
        @else
            <style>
                .ainpa-auth-locale-bar {
                    position: fixed;
                    top: 14px;
                    inset-inline-end: 18px;
                    z-index: 30;
                    display: inline-flex;
                    align-items: center;
                    padding: .30rem .55rem;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, .92);
                    color: #7f1d1d;
                    box-shadow: 0 8px 24px rgba(15, 23, 42, .10);
                }
            </style>
        @endif
    </head>
    <body class="ainpa-auth-body">
        <div class="ainpa-auth-locale-bar">
            {{-- Language switcher moved to Settings. --}}
        </div>

        <main class="ainpa-auth-stage">
            <section class="ainpa-auth-card" aria-label="{{ __('core.auth.region') }} - {{ $siteTitle }}">
                <a href="{{ route('front.home') }}" class="ainpa-auth-logo" aria-label="{{ $siteTitle }}">
                    <x-application-logo />
                    <span class="ainpa-auth-logo-fallback">
                        <strong>I.N.P.A</strong>
                        <small>{{ __('core.brand.network') }}</small>
                    </span>
                </a>

                {{ $slot }}
            </section>
        </main>
        @include('platform.plugin-assets', ['scope' => 'guest', 'kind' => 'scripts'])
    </body>
</html>
