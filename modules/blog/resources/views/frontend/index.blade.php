<x-frontend-layout>
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>{{ __('blog::ui.art-inpa') }}</title>
        <meta name="description" content="آخر أخبار ومقالات Art INPA والتغطيات الفنية والثقافية.">
        @php($robotsEmpty = isset($posts) && method_exists($posts, 'isEmpty') && $posts->isEmpty())
        <meta name="robots" content="{{ $robotsEmpty ? 'noindex, follow' : 'index, follow' }}">
        <link rel="canonical" href="{{ route('blog.index') }}">
        <meta property="og:type" content="website"><meta property="og:title" content="الأخبار والمقالات | Art INPA"><meta property="og:description" content="آخر أخبار ومقالات Art INPA والتغطيات الفنية والثقافية."><meta property="og:url" content="{{ route('blog.index') }}">
        <script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@'.'type' => 'CollectionPage', 'name' => 'الأخبار والمقالات | Art INPA', 'description' => 'آخر أخبار ومقالات Art INPA والتغطيات الفنية والثقافية.', 'url' => route('blog.index'), 'inLanguage' => str_replace('_', '-', app()->getLocale())], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <!-- blog-seo:end -->
        @include('blog::frontend.partials.styles')
    </x-slot>

    @if($renderedTemplate)
        <div class="blog-selected-template" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">{!! $renderedTemplate !!}</div>
        @include('blog::frontend.partials.pagination', ['posts' => $posts])
    @else
    <section class="blog-archive blog-archive--classic" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <div class="blog-shell">
            <nav class="blog-archive-breadcrumb" aria-label="مسار التنقل">
                <a href="{{ route('front.home') }}">الرئيسية</a>
                <span aria-hidden="true">/</span>
                <span>الأخبار والمقالات</span>
            </nav>

            <header class="blog-archive-title">
                <h1>الأخبار والمقالات</h1>
            </header>

            @include('blog::frontend.partials.post-grid', ['posts' => $posts])
        </div>
    </section>
    @endif
</x-frontend-layout>
