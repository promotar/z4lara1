{{-- Consume the existing core layout; never copy site headers or depend on another plugin. --}}
<x-frontend-layout>
    <x-slot name="head">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="referrer" content="no-referrer">
        <title>@yield('title', __('certificates::ui.certificates'))</title>
        <link rel="stylesheet" href="{{ route('certificates.styles', ['v' => '1.0.1']) }}">
    </x-slot>
    <div class="certificates-public" dir="rtl" lang="ar">
        <div class="certificate-page">@yield('content')</div>
    </div>
</x-frontend-layout>
