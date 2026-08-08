<x-frontend-layout>
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>الأخبار والمقالات | Art INPA</title>
        <meta name="description" content="آخر أخبار ومقالات Art INPA والتغطيات الفنية والثقافية.">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('blog.index') }}">
        <meta property="og:type" content="website"><meta property="og:title" content="الأخبار والمقالات | Art INPA"><meta property="og:description" content="آخر أخبار ومقالات Art INPA والتغطيات الفنية والثقافية."><meta property="og:url" content="{{ route('blog.index') }}">
        <script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@'.'type' => 'CollectionPage', 'name' => 'الأخبار والمقالات | Art INPA', 'description' => 'آخر أخبار ومقالات Art INPA والتغطيات الفنية والثقافية.', 'url' => route('blog.index'), 'inLanguage' => 'ar'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <!-- blog-seo:end -->
        <link rel="stylesheet" href="{{ route('blog.styles') }}">
    </x-slot>

    @if($renderedTemplate)
        <div class="blog-selected-template" lang="ar" dir="rtl">{!! $renderedTemplate !!}</div>
        @include('blog::frontend.partials.pagination', ['posts' => $posts])
    @else
    <section class="blog-archive blog-archive--classic" lang="ar" dir="rtl">
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
