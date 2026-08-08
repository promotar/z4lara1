<x-frontend-layout>
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>التصنيفات | أخبار Art INPA</title>
        <meta name="description" content="تصفح أخبار ومقالات Art INPA حسب التصنيف والموضوع.">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('blog.categories') }}">
        <meta property="og:type" content="website"><meta property="og:title" content="التصنيفات | أخبار Art INPA"><meta property="og:description" content="تصفح أخبار ومقالات Art INPA حسب التصنيف والموضوع."><meta property="og:url" content="{{ route('blog.categories') }}">
        <script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@'.'type' => 'CollectionPage', 'name' => 'التصنيفات | أخبار Art INPA', 'description' => 'تصفح أخبار ومقالات Art INPA حسب التصنيف والموضوع.', 'url' => route('blog.categories'), 'inLanguage' => 'ar'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <!-- blog-seo:end -->
        <link rel="stylesheet" href="{{ route('blog.styles') }}">
    </x-slot>

    <section class="blog-archive" lang="ar" dir="rtl">
        <div class="blog-shell">
            <header class="blog-archive-heading">
                <p class="blog-eyebrow">استكشف المحتوى</p>
                <h1>التصنيفات</h1>
                <p>تصفح تغطيات Art INPA حسب الموضوع والقسم التحريري والمجال الثقافي.</p>
            </header>

            <div class="blog-category-grid">
                @forelse ($categories as $category)
                    <a href="{{ route('blog.category', $category->slug) }}" class="blog-category-card">
                        @if ($category->image)
                            <img src="{{ $category->image }}" alt="{{ $category->image_alt ?: $category->name }}" style="width:100%;height:150px;object-fit:cover;border-radius:8px;margin-bottom:12px;">
                        @endif
                        <span>{{ $category->published_posts_count }} مقال</span>
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
