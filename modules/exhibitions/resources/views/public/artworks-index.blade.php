<x-frontend-layout>
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-public-catalog-1" data-exhibitions-stylesheet>

<main class="z4-public-artworks-index" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
    <section class="z4-artworks-index-hero">
        <div>
            <span class="z4-artworks-index-eyebrow">{{ __('exhibitions::ui.art-inpa-2') }}</span>
            <h1>الأعمال الفنية</h1>
            <p>اكتشف أعمالاً أصلية لفنانين مختارين، واطّلع على تفاصيل كل عمل وقصة الفنان وإمكانية الشراء.</p>
        </div>
    </section>

    <div class="z4-artworks-index-shell">
        <form class="z4-artworks-index-filters" method="get" action="{{ route('exhibitions.artworks.index') }}" aria-label="تصفية الأعمال الفنية">
            <label>
                <span>البحث</span>
                <input type="search" name="search" value="{{ $search }}" placeholder="اسم العمل، الفنان، أو الأسلوب">
            </label>
            <label>
                <span>التصنيف</span>
                <select name="category">
                    <option value="">كل التصنيفات</option>
                    @foreach($categories as $categoryOption)
                        <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                    @endforeach
                </select>
            </label>
            <div class="z4-artworks-index-filter-actions">
                <button type="submit">تطبيق</button>
                @if($search !== '' || $category !== '')
                    <a href="{{ route('exhibitions.artworks.index') }}">مسح التصفية</a>
                @endif
            </div>
        </form>

        @if($artworks->isEmpty())
            <section class="z4-artworks-index-empty">
                <strong>لا توجد أعمال مطابقة حالياً</strong>
                <p>جرّب تغيير كلمات البحث أو إزالة بعض خيارات التصفية.</p>
                <a href="{{ route('exhibitions.artworks.index') }}">عرض جميع الأعمال</a>
            </section>
        @else
            <section class="z4-artworks-index-grid" aria-label="نتائج الأعمال الفنية">
                @foreach($artworks as $artwork)
                    @php
                        $artistName = $artwork->artistic_name ?: $artwork->legal_name ?: 'فنان Art INPA';
                        $permalink = $artwork->slug ?: (\Illuminate\Support\Str::slug($artwork->title) ?: $artwork->public_id);
                        $price = rtrim(rtrim(number_format((float) $artwork->approved_amount_snapshot, 3, '.', ''), '0'), '.');
                    @endphp
                    <article class="z4-artworks-index-card" data-artwork-id="{{ $artwork->public_id }}" data-artwork-state="{{ $artwork->is_sold ? 'sold' : 'available' }}">
                        <a class="z4-artworks-index-image" href="{{ route('exhibitions.artworks.show', $permalink) }}" aria-label="عرض {{ $artwork->title }}">
                            @if($artwork->primary_media)
                                <img src="{{ route('exhibitions.artworks.media', [$permalink, $artwork->primary_media->public_id]) }}" alt="{{ $artwork->title }}" loading="lazy">
                            @else
                                <span aria-hidden="true">{{ __('exhibitions::ui.art') }}</span>
                            @endif
                            @if($artwork->is_sold)
                                <em class="is-sold">تم البيع</em>
                            @else
                                <em class="is-available">متاح للشراء</em>
                            @endif
                        </a>
                        <div class="z4-artworks-index-card-body">
                            <div class="z4-artworks-index-meta">
                                <span>{{ $artwork->category ?: 'عمل فني' }}</span>
                                @if($artwork->completion_year)<span>{{ $artwork->completion_year }}</span>@endif
                            </div>
                            <h2><a href="{{ route('exhibitions.artworks.show', $permalink) }}">{{ $artwork->title }}</a></h2>
                            <p>بواسطة {{ $artistName }}</p>
                            @if($artwork->short_description)<p class="z4-artworks-index-description">{{ $artwork->short_description }}</p>@endif
                            <footer>
                                <strong>{{ $price }} {{ $artwork->currency }}</strong>
                                <a href="{{ route('exhibitions.artworks.show', $permalink) }}">عرض العمل <span aria-hidden="true">←</span></a>
                            </footer>
                        </div>
                    </article>
                @endforeach
            </section>

            @if($artworks->hasPages())
                <x-platform-pagination :paginator="$artworks" />
            @endif
        @endif
    </div>
</main>
</x-frontend-layout>
