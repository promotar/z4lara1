<x-frontend-layout>
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>#{{ $tag->name }} | أخبار Art INPA</title>
        <meta name="description" content="مقالات Art INPA المرتبطة بوسم {{ $tag->name }}.">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('blog.tag', $tag->slug) }}">
        <meta property="og:type" content="website"><meta property="og:title" content="#{{ $tag->name }} | أخبار Art INPA"><meta property="og:description" content="مقالات Art INPA المرتبطة بوسم {{ $tag->name }}."><meta property="og:url" content="{{ route('blog.tag', $tag->slug) }}">
        <script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@'.'type' => 'CollectionPage', 'name' => '#'.$tag->name.' | أخبار Art INPA', 'description' => 'مقالات Art INPA المرتبطة بوسم '.$tag->name.'.', 'url' => route('blog.tag', $tag->slug), 'inLanguage' => 'ar'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <!-- blog-seo:end -->
        <link rel="stylesheet" href="{{ route('blog.styles') }}">
    </x-slot>

    @if($renderedTemplate)
        <div class="blog-selected-template" lang="ar" dir="rtl">{!! $renderedTemplate !!}</div>
        @include('blog::frontend.partials.pagination', ['posts' => $posts])
    @else
    <section class="blog-archive" lang="ar" dir="rtl">
        <div class="blog-shell">
            <header class="blog-archive-heading">
                <a class="blog-back-link" href="{{ route('blog.index') }}">الأخبار والمقالات</a>
                <p class="blog-eyebrow">الوسم</p>
                <h1><bdi>#{{ $tag->name }}</bdi></h1>
                <p>القصص والملاحظات والتحديثات التحريرية المرتبطة بهذا الموضوع.</p>
            </header>

            @include('blog::frontend.partials.post-grid', ['posts' => $posts])
        </div>
    </section>
    @endif
</x-frontend-layout>
