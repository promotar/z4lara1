<x-frontend-layout>
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>{{ __('blog::ui.art-inpa-2') }}</title>
        <meta name="description" content="{{ __('blog::ui.categories-meta-description') }}">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('blog.categories') }}">
        <meta property="og:type" content="website"><meta property="og:title" content="{{ __('blog::ui.art-inpa-2') }}"><meta property="og:description" content="{{ __('blog::ui.categories-meta-description') }}"><meta property="og:url" content="{{ route('blog.categories') }}">
        <script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@'.'type' => 'CollectionPage', 'name' => __('blog::ui.art-inpa-2'), 'description' => __('blog::ui.categories-meta-description'), 'url' => route('blog.categories'), 'inLanguage' => str_replace('_', '-', app()->getLocale())], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <!-- blog-seo:end -->
        @include('blog::frontend.partials.styles')
    </x-slot>

    <section class="blog-archive" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <div class="blog-shell">
            <header class="blog-archive-heading">
                <p class="blog-eyebrow">استكشف المحتوى</p>
                <h1>التصنيفات</h1>
                <p>{{ __('blog::ui.art-inpa-3') }}</p>
            </header>

            <div class="blog-category-grid">
                @forelse ($categories as $category)
                    <a href="{{ route('blog.category', $category->slug) }}" class="blog-category-card">
                        @if ($category->image)
                            <img src="{{ $category->image }}" alt="{{ $category->image_alt ?: $category->name }}" style="width:100%;height:150px;object-fit:cover;border-radius:8px;margin-bottom:12px;">
                        @endif
                        <span>{{ trans_choice('blog::ui.articles-count', (int) $category->published_posts_count, ['count' => $category->published_posts_count]) }}</span>
                        <strong><bdi>{{ $category->name }}</bdi></strong>
                        @if ($category->description)
                            <p>{{ $category->description }}</p>
                        @else
                            <p>اطلع على أحدث المواد المنشورة ضمن هذا التصنيف.</p>
                        @endif
                    </a>
                @empty
                    <p class="blog-empty">لا توجد تصنيفات متاحة حتى الآن.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-frontend-layout>
