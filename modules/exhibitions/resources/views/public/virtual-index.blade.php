<x-frontend-layout>
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-virtual-index-1" data-exhibitions-stylesheet>

<main class="z4-public-virtual-index" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
    <section class="z4-virtual-index-hero">
        <div>
            <span>{{ __('exhibitions::ui.art-inpa') }}</span>
            <h1>المعرض الفني الافتراضي</h1>
            <p>تجوّل بحرية داخل قاعاتنا الرقمية، اقترب من الأعمال الفنية، واكتشف قصص الفنانين وتفاصيل أعمالهم.</p>
        </div>
        <div class="z4-virtual-index-count" aria-label="عدد القاعات المنشورة">
            <strong>{{ $rooms->count() }}</strong>
            <span>{{ $rooms->count() === 1 ? 'قاعة متاحة' : 'قاعات متاحة' }}</span>
        </div>
    </section>

    <section class="z4-virtual-index-shell" aria-labelledby="virtual-rooms-heading">
        <header class="z4-virtual-index-heading">
            <div>
                <span>المعارض المتاحة للزيارة</span>
                <h2 id="virtual-rooms-heading">اختر القاعة وابدأ جولتك</h2>
            </div>
            <a href="{{ route('exhibitions.artworks.index') }}">تصفح جميع الأعمال الفنية</a>
        </header>

        @if($rooms->isEmpty())
            <div class="z4-virtual-index-empty">
                <strong>لا توجد قاعات منشورة حاليًا</strong>
                <p>ستظهر المعارض الجديدة هنا فور اعتمادها ونشرها.</p>
            </div>
        @else
            <div class="z4-virtual-index-grid">
                @foreach($rooms as $room)
                    <article class="z4-virtual-index-card">
                        <div class="z4-virtual-index-card-visual" aria-hidden="true">
                            <span>{{ __('exhibitions::ui.3d') }}</span>
                            <b>جولة حرة</b>
                        </div>
                        <div class="z4-virtual-index-card-body">
                            <span>معرض افتراضي</span>
                            <h3>{{ $room->title }}</h3>
                            <p>{{ $room->short_description ?: 'قاعة فنية رقمية تفاعلية تتيح لك استكشاف الأعمال المعروضة والتعرّف إلى الفنانين.' }}</p>
                            <dl>
                                <div><dt>الأعمال المعروضة</dt><dd>{{ (int) $room->artworks_count }}</dd></div>
                                <div><dt>مساحات العرض</dt><dd>{{ (int) $room->displays_count }}</dd></div>
                            </dl>
                            <a href="{{ route('exhibitions.virtual.show', $room->slug) }}">دخول المعرض</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</main>
</x-frontend-layout>
