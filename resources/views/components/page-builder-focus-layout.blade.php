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

@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('platform.plugin-assets', ['scope' => 'admin', 'kind' => 'styles'])
        {{ $styles ?? '' }}
        @stack('styles')
    </head>
    <body class="font-sans antialiased page-builder-focus-body page-builder-sidebar-compact" data-page-builder-route="admin-pages-edit">
        <script>
            (() => {
                try {
                    if (window.localStorage.getItem('z4-page-builder-sidebar') === 'expanded') {
                        document.body.classList.remove('page-builder-sidebar-compact');
                        document.body.classList.add('page-builder-sidebar-expanded');
                    }
                } catch (error) {
                    //
                }
            })();
        </script>
        @include('layouts.navigation')

        <main class="page-builder-main page-builder-focus-main">
            {{ $slot }}
        </main>
        @include('platform.plugin-assets', ['scope' => 'admin', 'kind' => 'scripts'])
    </body>
</html>
