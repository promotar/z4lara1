<x-frontend-layout>
    @php
        $categoryTitle = $category->seo_title ?: $category->name.' | أخبار Art INPA';
        $categoryDescription = $category->seo_description ?: $category->description ?: 'المقالات والتحديثات المنشورة ضمن هذا التصنيف.';
        $categoryUrl = route('blog.category', $category->slug);
        $categoryImage = $category->image ? (str_starts_with($category->image, 'http') ? $category->image : url($category->image)) : null;
        $categorySchema = ['@'.'context' => 'https://schema.org', '@'.'type' => 'CollectionPage', 'name' => $categoryTitle, 'description' => $categoryDescription, 'url' => $categoryUrl, 'inLanguage' => str_replace('_', '-', app()->getLocale()), 'primaryImageOfPage' => $categoryImage ? ['@'.'type' => 'ImageObject', 'url' => $categoryImage] : null];
        $categorySchema = array_filter($categorySchema, fn ($value) => $value !== null);
    @endphp
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>{{ $categoryTitle }}</title>
        <meta name="description" content="{{ $categoryDescription }}">
        @php($robotsEmpty = isset($posts) && method_exists($posts, 'isEmpty') && $posts->isEmpty())
        <meta name="robots" content="{{ $robotsEmpty ? 'noindex, follow' : 'index, follow' }}">
        <link rel="canonical" href="{{ $categoryUrl }}">
        <meta property="og:type" content="website"><meta property="og:title" content="{{ $categoryTitle }}"><meta property="og:description" content="{{ $categoryDescription }}"><meta property="og:url" content="{{ $categoryUrl }}">
        @if($categoryImage)<meta property="og:image" content="{{ $categoryImage }}"><meta name="twitter:card" content="summary_large_image">@else<meta name="twitter:card" content="summary">@endif
        <script type="application/ld+json">{!! json_encode($categorySchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <!-- blog-seo:end -->
        @include('blog::frontend.partials.styles')
    </x-slot>

    @if($renderedTemplate)
        <div class="blog-selected-template" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">{!! $renderedTemplate !!}</div>
        @include('blog::frontend.partials.pagination', ['posts' => $posts])
    @else
    <section class="blog-archive" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <div class="blog-shell">
            <header class="blog-archive-heading">
                <a class="blog-back-link" href="{{ route('blog.categories') }}">كل التصنيفات</a>
                <p class="blog-eyebrow">التصنيف</p>
                <h1><bdi>{{ $category->name }}</bdi></h1>
                @if ($category->image)
                    <img src="{{ $category->image }}" alt="{{ $category->image_alt ?: $category->name }}" style="width:100%;max-height:360px;object-fit:cover;border-radius:12px;margin:18px 0;">
                @endif
                @if ($category->description)
                    <p>{{ $category->description }}</p>
                @else
                    <p>المقالات والتحديثات المنشورة ضمن هذا التصنيف.</p>
                @endif
            </header>

            @include('blog::frontend.partials.post-grid', ['posts' => $posts])
        </div>
    </section>
    @endif
</x-frontend-layout>
