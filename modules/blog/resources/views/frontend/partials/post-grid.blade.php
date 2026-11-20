<div class="blog-post-grid" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
    @forelse ($posts as $post)
        @php
            $imageUrl = $post->featuredImageUrl();
            $categoryName = $post->category?->name ?: 'Art INPA';
            $categoryUrl = $post->category ? route('blog.category', $post->category->slug) : route('blog.index');
            $authorName = $post->author?->name ?: 'Art INPA';
            $authorInitial = function_exists('mb_substr')
                ? mb_substr($authorName, 0, 1, 'UTF-8')
                : substr($authorName, 0, 1);
            $commentCount = $post->comments_count ?? 0;
            $commentLabel = $commentCount === 1 ? 'تعليق واحد' : $commentCount.' تعليقات';
        @endphp

        <article class="blog-post-card">
            <div class="blog-post-card__media-wrap">
                <a href="{{ route('blog.show', $post->slug) }}" class="blog-post-card__media" aria-label="قراءة: {{ $post->title }}">
                    @if ($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" decoding="async">
                    @else
                        <span class="blog-post-card__placeholder"><bdi>{{ $categoryName }}</bdi></span>
                    @endif
                </a>

                <a class="blog-post-card__category" href="{{ $categoryUrl }}"><bdi>{{ $categoryName }}</bdi></a>
            </div>

            <div class="blog-post-card__body">
                <h2><a href="{{ route('blog.show', $post->slug) }}"><bdi>{{ $post->title }}</bdi></a></h2>

                <div class="blog-post-meta">
                    <span class="blog-post-author">
                        <span class="blog-post-author__avatar" aria-hidden="true">{{ $authorInitial }}</span>
                        <span><bdi>{{ $authorName }}</bdi></span>
                    </span>

                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->locale(app()->getLocale())->translatedFormat('j F Y') }}</time>
                    @endif

                    <span class="blog-post-comments" aria-label="{{ $commentLabel }}">
                        <span class="blog-post-comments__icon" aria-hidden="true"></span>
                        <span>{{ $commentCount }}</span>
                    </span>
                </div>
            </div>
        </article>
    @empty
        @php($emptyEn = str_starts_with((string) app()->getLocale(), 'en'))
        <div class="blog-empty-state" role="status" style="grid-column:1/-1;display:flex;flex-direction:column;align-items:center;gap:10px;margin:8px 0 4px;border:1px dashed #e2cfcf;border-radius:18px;background:linear-gradient(180deg,#fffdfd 0%,#fff8f8 100%);padding:56px 24px;text-align:center">
            <span class="blog-empty-state__mark" aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:62px;height:62px;max-width:62px;max-height:62px;flex:0 0 auto;border-radius:999px;background:#fff;box-shadow:0 10px 26px rgba(127,29,29,.09);color:var(--art-color-accent,#a90000);overflow:hidden">
                <svg width="30" height="30" viewBox="0 0 24 24" style="width:30px;height:30px;max-width:30px;max-height:30px" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H16l4 4v14.5A1.5 1.5 0 0 1 18.5 24h-13A1.5 1.5 0 0 1 4 22.5Z" transform="translate(0 -2)"/>
                    <path d="M8 9h7M8 13h9M8 17h5" transform="translate(0 -2)"/>
                </svg>
            </span>

            <p class="blog-empty-state__eyebrow" style="margin:6px 0 0;color:var(--art-color-accent,#a90000);font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase">Art INPA</p>

            <h2 class="blog-empty-state__title" style="margin:0;color:#261d1d;font-size:clamp(22px,3vw,30px);line-height:1.35">
                {{ $emptyEn ? 'No articles here yet' : 'لا توجد مقالات هنا بعد' }}
            </h2>

            <p class="blog-empty-state__body" style="max-width:52ch;margin:0;color:#7b6868;font-size:15px;line-height:1.8">
                {{ $emptyEn
                    ? 'Nothing has been published in this section so far. Browse the other sections or check back soon.'
                    : 'لم يُنشر في هذا القسم أي مقال حتى الآن. تصفّح بقية الأقسام أو عُد قريباً.' }}
            </p>

            <div class="blog-empty-state__actions" style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:14px">
                <a class="blog-empty-state__button" href="{{ route('blog.index') }}" style="display:inline-flex;align-items:center;justify-content:center;min-height:42px;border:1px solid transparent;border-radius:10px;background:var(--art-color-accent,#a90000);padding:0 20px;color:#fff;font-size:14px;font-weight:700;text-decoration:none">
                    {{ $emptyEn ? 'All articles' : 'كل المقالات' }}
                </a>
                <a class="blog-empty-state__button blog-empty-state__button--ghost" href="{{ route('blog.categories') }}" style="display:inline-flex;align-items:center;justify-content:center;min-height:42px;border:1px solid #e6d3d3;border-radius:10px;background:#fff;padding:0 20px;color:#6b5555;font-size:14px;font-weight:700;text-decoration:none">
                    {{ $emptyEn ? 'All categories' : 'كل التصنيفات' }}
                </a>
            </div>
        </div>
    @endforelse
</div>

@include('blog::frontend.partials.pagination', ['posts' => $posts])
