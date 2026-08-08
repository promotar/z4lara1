<x-frontend-layout>
    @php
        $preview = $preview ?? false;
        $renderedTemplate = $renderedTemplate ?? null;
        $relatedPosts = $relatedPosts ?? collect();
        $metaTitle = $post->seo_title ?: $post->meta_title ?: $post->title;
        $metaDescription = $post->seo_description ?: $post->meta_description ?: $post->excerpt;
        $robots = ($post->robots_index ? 'index' : 'noindex').', '.($post->robots_follow ? 'follow' : 'nofollow');
        $imageUrl = $post->featuredImageUrl();
        $canonical = $post->canonical_url ?: route('blog.show', $post->slug);
        $absoluteImageUrl = $imageUrl ? (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://') ? $imageUrl : url($imageUrl)) : null;
        try {
            $siteSettings = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
        } catch (\Throwable $exception) {
            $siteSettings = [];
        }
        $siteName = $siteSettings['general.site_title'] ?? config('app.name', 'Art INPA');
        $publisherLogo = $siteSettings['general.site_logo'] ?? null;
        $publisher = ['@'.'type' => 'Organization', 'name' => $siteName, 'url' => url('/')];
        if ($publisherLogo) {
            $publisher['logo'] = ['@'.'type' => 'ImageObject', 'url' => str_starts_with($publisherLogo, 'http') ? $publisherLogo : url($publisherLogo)];
        }
        $schema = [
            '@'.'context' => 'https://schema.org',
            '@'.'graph' => [
                [
                    '@'.'type' => in_array($post->schema_type, ['BlogPosting', 'Article', 'NewsArticle'], true) ? $post->schema_type : 'BlogPosting',
                    '@'.'id' => $canonical.'#article',
                    'headline' => $post->title,
                    'name' => $metaTitle,
                    'description' => $metaDescription,
                    'url' => $canonical,
                    'mainEntityOfPage' => ['@'.'type' => 'WebPage', '@'.'id' => $canonical],
                    'image' => $absoluteImageUrl ? ['@'.'type' => 'ImageObject', 'url' => $absoluteImageUrl] : null,
                    'author' => $post->author?->name ? ['@'.'type' => 'Person', 'name' => $post->author->name] : $publisher,
                    'publisher' => $publisher,
                    'datePublished' => optional($post->published_at ?: $post->created_at)->toIso8601String(),
                    'dateModified' => optional($post->updated_at)->toIso8601String(),
                    'articleSection' => $post->category?->name,
                    'keywords' => $post->tags->pluck('name')->filter()->values()->all(),
                    'inLanguage' => 'ar',
                ],
                [
                    '@'.'type' => 'BreadcrumbList',
                    '@'.'id' => $canonical.'#breadcrumb',
                    'itemListElement' => [
                        ['@'.'type' => 'ListItem', 'position' => 1, 'name' => 'الرئيسية', 'item' => route('front.home')],
                        ['@'.'type' => 'ListItem', 'position' => 2, 'name' => 'الأخبار والمقالات', 'item' => route('blog.index')],
                        ['@'.'type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => $canonical],
                    ],
                ],
            ],
        ];
        $schema['@'.'graph'][0] = array_filter($schema['@'.'graph'][0], fn ($value) => $value !== null && $value !== []);
    @endphp

    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>{{ $metaTitle }}</title>
        @if ($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endif
        <meta name="robots" content="{{ $preview ? 'noindex, nofollow' : $robots }}">
        <link rel="canonical" href="{{ $canonical }}">
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:title" content="{{ $post->seo_social_title ?: $metaTitle }}">
        <meta property="og:url" content="{{ $canonical }}">
        @if ($post->seo_social_description ?: $metaDescription)
            <meta property="og:description" content="{{ $post->seo_social_description ?: $metaDescription }}">
        @endif
        @if ($absoluteImageUrl)
            <meta property="og:image" content="{{ $absoluteImageUrl }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $absoluteImageUrl }}">
        @else
            <meta name="twitter:card" content="summary">
        @endif
        <meta name="twitter:title" content="{{ $post->seo_social_title ?: $metaTitle }}">
        @if ($post->seo_social_description ?: $metaDescription)<meta name="twitter:description" content="{{ $post->seo_social_description ?: $metaDescription }}">@endif
        @if ($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">@endif
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
        @if ($post->category)<meta property="article:section" content="{{ $post->category->name }}">@endif
        @foreach ($post->tags as $tag)<meta property="article:tag" content="{{ $tag->name }}">@endforeach
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS) !!}</script>
        <!-- blog-seo:end -->
        <link rel="stylesheet" href="{{ route('blog.styles') }}">
    </x-slot>

    @if($renderedTemplate && !$preview)
        <div class="blog-selected-template" lang="ar" dir="rtl">{!! $renderedTemplate !!}</div>
    @else
    <article class="blog-single" lang="ar" dir="rtl">
        <div class="blog-shell blog-single__shell">
            @if ($preview)
                <div class="blog-preview-alert" role="status">وضع المعاينة: قد لا يكون هذا المقال منشورًا للعامة.</div>
            @endif

            <header class="blog-single-header">
                <a class="blog-back-link" href="{{ route('blog.index') }}">الأخبار والمقالات</a>

                <div class="blog-post-meta">
                    @if ($post->category)
                        <a href="{{ route('blog.category', $post->category->slug) }}"><bdi>{{ $post->category->name }}</bdi></a>
                    @endif
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->locale('ar')->translatedFormat('j F Y') }}</time>
                    @endif
                    @if ($post->author)
                        <span><bdi>{{ $post->author->name }}</bdi></span>
                    @endif
                </div>

                <h1><bdi>{{ $post->title }}</bdi></h1>

                @if ($post->excerpt)
                    <p>{{ $post->excerpt }}</p>
                @endif
            </header>

            @if ($imageUrl)
                <figure class="blog-single-media">
                    <img src="{{ $imageUrl }}" alt="{{ $post->featured_image_alt ?: $post->featuredImage?->alt_text ?: $post->title }}" fetchpriority="high" decoding="async">
                    @if ($post->featuredImage?->caption)
                        <figcaption>{{ $post->featuredImage->caption }}</figcaption>
                    @endif
                </figure>
            @endif

            <div class="blog-single-layout">
                <div class="blog-article-body">
                    {!! $post->content !!}
                </div>

                <aside class="blog-single-sidebar" aria-label="معلومات المقال">
                    <div class="blog-sidebar-card">
                        <span>نُشر في</span>
                        <strong><bdi>{{ $post->category?->name ?: 'أخبار Art INPA' }}</bdi></strong>
                    </div>

                    @if ($post->tags->isNotEmpty())
                        <div class="blog-sidebar-card">
                            <span>الوسوم</span>
                            <div class="blog-tag-list">
                                @foreach ($post->tags as $tag)
                                    <a href="{{ route('blog.tag', $tag->slug) }}"><bdi>#{{ $tag->name }}</bdi></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </article>

    @if ($relatedPosts->isNotEmpty())
        <section class="blog-related" lang="ar" dir="rtl">
            <div class="blog-shell">
                <header class="blog-section-heading">
                    <p class="blog-eyebrow">من التصنيف نفسه</p>
                    <h2>مقالات قد تعجبك</h2>
                </header>

                @include('blog::frontend.partials.post-grid', ['posts' => $relatedPosts])
            </div>
        </section>
    @endif
    @endif
</x-frontend-layout>
