<x-frontend-layout>
    <x-slot name="head">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="referrer" content="no-referrer">
        <title>{{ $pageTitle }}</title>
        <link rel="stylesheet" href="{{ route('certificates.styles', ['v' => '1.2.0']) }}">
    </x-slot>
    <div class="certificates-public" dir="rtl" lang="ar">
        <div class="certificate-page">{!! $renderedTemplate !!}</div>
    </div>
</x-frontend-layout>
