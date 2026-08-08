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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('auth-brand-overrides.css') }}?v=20260721-account-responsive-audit-3">
        @include('platform.plugin-assets', ['scope' => 'guest', 'kind' => 'styles'])
    </head>
    <body class="ainpa-auth-body">
        <main class="ainpa-auth-stage">
            <section class="ainpa-auth-card" aria-label="{{ $siteTitle }} authentication">
                <a href="{{ route('front.home') }}" class="ainpa-auth-logo" aria-label="{{ $siteTitle }}">
                    <x-application-logo />
                    <span class="ainpa-auth-logo-fallback">
                        <strong>I.N.P.A</strong>
                        <small>International Network for Plastic Art</small>
                    </span>
                </a>

                {{ $slot }}
            </section>
        </main>
        @include('platform.plugin-assets', ['scope' => 'guest', 'kind' => 'scripts'])
    </body>
</html>
