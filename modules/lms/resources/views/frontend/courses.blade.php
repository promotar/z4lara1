<x-frontend-layout>
    <x-slot name="head">
        <title>{{ $catalogTitle }} - {{ config('app.name') }}</title>
        <meta name="description" content="{{ $catalogDescription }}">
    </x-slot>

    <main
        class="lms-selected-catalog-template"
        lang="{{ str_replace('_', '-', app()->getLocale()) }}"
        dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}"
    >
        @if($renderedTemplate)
            {!! $renderedTemplate !!}
        @else
            <section role="status" style="max-width:72rem;margin:3rem auto;padding:1.5rem;text-align:center">
                <h1>{{ $catalogTitle }}</h1>
                <p>{{ $catalogDescription }}</p>
            </section>
        @endif
    </main>
</x-frontend-layout>
