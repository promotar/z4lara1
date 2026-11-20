@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-quick-2" data-exhibitions-stylesheet>@endpush
@php
    $editing = $artwork !== null;
    $v = fn (string $key, mixed $default = null) => old($key, $artwork?->{$key} ?? $default);
    $primaryMedia = $primaryMedia ?? null;
    $photoRequired = ! ($hasPrimaryImage ?? false);
    $confirmed = (bool) old('ownership_confirmed', $declarationConfirmed ?? false);
    $categories = $categories ?? ['Painting', 'Sculpture', 'Mixed media', 'Photography'];
    $categoryLabels = $categoryLabels ?? [];
    $edition = (string) $v('edition_type', 'original');
    $limited = in_array($edition, ['limited', 'limited_edition'], true);
@endphp
<x-frontend-layout><main class="z4-exhibitions-admin z4-artist-dashboard z4-artwork-quick"><div class="z4-exhibitions-container z4-exhibitions-form-container">
@include('exhibitions::artist.dashboard._nav',['title'=>$editing?'تعديل العمل':'إضافة عمل جديد'])
@if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert"><strong>يرجى تصحيح ما يلي:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(isset($sellingAllowed) && !$sellingAllowed)<div class="z4-exhibitions-alert is-warning"><strong>لا يمكن الإرسال للمراجعة بعد</strong><p>يجب اعتماد حسابك كفنان قبل إرسال الأعمال. يمكنك حفظ مسودة الآن.</p><a href="{{ route('exhibitions.application.status') }}">عرض حالة الطلب</a></div>@endif
<form class="z4-quick-form" dir="rtl" method="post" enctype="multipart/form-data" action="{{ $editing ? route('exhibitions.dashboard.artworks.update',$artwork->public_id) : route('exhibitions.dashboard.artworks.store') }}">
@csrf
@php($activeArtworkLocale = $locale ?? app()->getLocale())
<input type="hidden" name="locale" value="{{ $activeArtworkLocale }}">
<input type="hidden" name="switch_locale" value="" data-artwork-switch-locale>
<input type="hidden" name="draft_key" value="{{ old('draft_key',$draftKey) }}">
<input type="hidden" name="lock_version" value="{{ old('lock_version',$artwork?->lock_version) }}">
@if(isset($locales) && count($locales) > 1)
<div class="z4-quick-lang-tabs">
    @foreach($locales as $code => $name)
        <a href="{{ $editing ? route('exhibitions.dashboard.artworks.edit', ['artwork' => $baseArtwork->public_id, 'locale' => $code]) : '#' }}"
           @if(!$editing) data-artwork-locale="{{ $code }}" @endif
           class="{{ $activeArtworkLocale === $code ? 'is-active' : '' }}"
           @if($activeArtworkLocale === $code) style="background:#690707!important;color:#fff!important;border-color:#690707!important" @endif>
            {{ $name }}
            <span>{{ $code === ($defaultLocale ?? 'en') ? '' : (in_array($code, $translatedLocales ?? [], true) ? '?' : '+') }}</span>
        </a>
    @endforeach
</div>
@if($activeArtworkLocale !== ($defaultLocale ?? 'en'))
<p class="z4-quick-lang-note">يتم حفظ اسم العمل والوصف النصي لهذه اللغة فقط، بينما تبقى الصور والسعر والبيانات المشتركة كما هي.</p>
@endif
@endif
<label class="z4-quick-photo">
    <span class="z4-quick-photo-title">صور العمل</span>
    @if($primaryMedia)
        <img class="z4-quick-photo-preview" src="{{ URL::temporarySignedRoute('exhibitions.dashboard.artworks.media.display', now()->addMinutes(10), ['artwork'=>$artwork->public_id,'media'=>$primaryMedia->public_id]) }}" alt="صورة العمل">
    @else
        <span class="z4-quick-photo-plus">+</span>
        <span class="z4-quick-photo-hint">اضغط لاختيار صورة من جهازك</span>
    @endif
    @if($editing && ($mediaCount ?? 0) > 0)<small class="z4-quick-photo-note">{{ $mediaCount }} صورة محفوظة حاليًا</small>@endif
    <input type="file" name="primary_images[]" accept="image/*" multiple @if($photoRequired) required @endif>
    <small class="z4-quick-photo-note">يمكنك اختيار عدة صور معًا: الصورة الرئيسية + تفاصيل + توقيع.</small>
</label>
<label class="z4-quick-field"><span>اسم العمل</span><input name="title" maxlength="180" value="{{ $v('title') }}" required placeholder="مثال: غروب على البحر">@error('title')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
<label class="z4-quick-field"><span>السعر (دينار)</span><input type="number" step="0.01" min="0.01" name="requested_price" value="{{ $v('requested_price') }}" required placeholder="مثال: 150">@error('requested_price')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
<label class="z4-quick-field"><span>وصف قصير</span><textarea name="short_description" rows="3" maxlength="500" placeholder="اكتب بكلماتك البسيطة عن العمل...">{{ $v('short_description') }}</textarea>@error('short_description')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
<div class="z4-quick-grid">
    <label class="z4-quick-field"><span>سنة الإنجاز</span><input type="number" name="completion_year" min="1000" max="{{ date('Y') }}" value="{{ $v('completion_year', date('Y')) }}" required>@error('completion_year')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
    <label class="z4-quick-field"><span>التصنيف</span><select name="category" data-category required><option value="">اختر...</option>@foreach($categories as $category)<option value="{{ $category }}" @selected($v('category')===$category)>{{ $categoryLabels[$category] ?? $category }}</option>@endforeach<option value="__other__" @selected(filled($v('category')) && ! in_array($v('category'), $categories, true))>أخرى — إضافة تصنيف جديد</option></select><input name="category_new" data-category-new placeholder="اكتب التصنيف الجديد هنا" maxlength="100" value="{{ old('category_new') }}" @if(! (filled($v('category')) && ! in_array($v('category'), $categories, true))) hidden @endif>@error('category')<small class="z4-quick-error">{{ $message }}</small>@enderror
@error('category_new')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
    <label class="z4-quick-field"><span>قطعة واحدة أم عدة نسخ؟</span><select name="edition_type" data-edition required><option value="original" @selected($edition==='original')>قطعة واحدة فقط (لن تتكرر)</option><option value="limited" @selected($limited)>عدة نسخ متشابهة (طبعة محدودة)</option></select><small class="z4-quick-help">«قطعة واحدة» = عمل فريد لن يُعاد إنتاجه. «عدة نسخ» = لديك أكثر من نسخة مطابقة من نفس العمل.</small></label>
    <label class="z4-quick-field"><span>الاتجاه</span><select name="orientation" required><option value="">اختر...</option><option value="portrait" @selected($v('orientation')==='portrait')>طولي (أطول من عرضه)</option><option value="landscape" @selected($v('orientation')==='landscape')>عرضي (أعرض من طوله)</option><option value="square" @selected($v('orientation')==='square')>مربّع</option><option value="panoramic" @selected($v('orientation')==='panoramic')>بانورامي</option><option value="other" @selected($v('orientation')==='other')>آخر</option></select>@error('orientation')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
    <label class="z4-quick-field"><span>حالة العمل</span><select name="condition" required><option value="new" @selected($v('condition','new')==='new')>جديد</option><option value="excellent" @selected($v('condition')==='excellent')>ممتاز</option><option value="good" @selected($v('condition')==='good')>جيد</option><option value="fair" @selected($v('condition')==='fair')>مقبول</option><option value="restored" @selected($v('condition')==='restored')>مُرمَّم</option></select></label>
    <label class="z4-quick-field"><span>الإتاحة</span><select name="availability" data-availability required><option value="available" @selected($v('availability','available')==='available')>متاح — معروض للبيع</option><option value="sold" @selected($v('availability')==='sold')>مباع — لن يُعرض للبيع</option><option value="unavailable" @selected($v('availability')==='unavailable')>غير متاح — يُحذف من المتجر</option></select></label>
    <div class="z4-quick-sold" data-sold @if($v('availability')!=='sold') hidden @endif>
        <label class="z4-quick-field"><span>اسم الشخص الذي اشترى اللوحة</span><input name="sold_buyer_name" maxlength="180" value="{{ $v('sold_buyer_name') }}" placeholder="مثال: محمد أحمد" @if($v('availability')==='sold') required @endif>@error('sold_buyer_name')<small class="z4-quick-error">{{ $message }}</small>@enderror</label>
    </div>
    <div class="z4-quick-limited" data-limited @if(!$limited) hidden @endif>
        <label class="z4-quick-field"><span>الكمية (عدد النسخ في هذه الطبعة)</span><input type="number" min="1" max="100000" name="edition_total" value="{{ $v('edition_total') }}" placeholder="10" data-edition-total><small class="z4-quick-help">مثلاً: 10 يعنى أنك ستبيع 10 نسخ متشابهة من هذا العمل.</small></label>
    </div>
</div>
<label class="z4-quick-confirm"><input type="hidden" name="ownership_confirmed" value="0"><input type="checkbox" name="ownership_confirmed" value="1" @checked($confirmed)><span class="z4-quick-confirm-text"><b>أُقرّ بأن هذا العمل من صنعي وأنا مالكه، وأن الصورة المعروضة حقيقية له، وأتعهّد بتسليم اللوحة للمؤسسة عند بيعها من متجرها، كما أتحمّل مسؤولية متابعة اللوحة وتحديث حالتها إذا قمت ببيعها من طرفي.</b></span></label>
@error('ownership_basis')<small class="z4-quick-error">يجب تأكيد الملكية قبل الإرسال.</small>@enderror
@error('primary_images')<small class="z4-quick-error">{{ $message }}</small>@enderror
@error('primary_images.*')<small class="z4-quick-error">{{ $message }}</small>@enderror
@error('artwork')<small class="z4-quick-error">{{ $message }}</small>@enderror
<div class="z4-quick-actions">
    <button class="z4-quick-btn is-secondary" type="submit" name="intent" value="save" formnovalidate>حفظ كمسودة</button>
    <button class="z4-quick-btn is-primary" type="submit" name="intent" value="submit">{{ ($autoApprove ?? false) ? 'نشر العمل' : 'إرسال للمراجعة' }}</button>
</div>
@if($autoApprove ?? false)<p class="z4-quick-more">حسابك معتمد للنشر التلقائي: بمجرد الضغط على «نشر العمل» يُعتمد العمل ويظهر في المتجر مباشرة.</p>@endif
@if($editing)<p class="z4-quick-more">تحتاج إضافة صور أخرى (تفاصيل / توقيع / خلفية)؟ <a href="{{ route('exhibitions.dashboard.artworks.media.index',$artwork->public_id) }}">إدارة صور العمل</a></p>@endif
</form>
</div></main></x-frontend-layout>
<style>
.z4-artwork-quick .z4-quick-form{max-width:680px;margin:0 auto;display:grid;gap:16px}
.z4-artwork-quick .z4-quick-lang-tabs{display:flex;flex-wrap:wrap;gap:6px}
.z4-artwork-quick .z4-quick-lang-tabs a{display:inline-flex;align-items:center;gap:6px;border:1px solid #e3d6d6;border-radius:7px;background:#fff;padding:9px 14px;color:#50575e;font-size:13px;font-weight:800;text-decoration:none}
.z4-artwork-quick .z4-quick-lang-tabs a.is-active{border-color:#690707!important;background:#690707!important;color:#fff!important}
.z4-artwork-quick .z4-quick-lang-tabs a span{font-size:10px;opacity:.8}
.z4-artwork-quick .z4-quick-lang-note{margin:0;border-radius:9px;background:#fff7ed;padding:10px 13px;color:#7c4a03;font-size:14px;line-height:1.6}
.z4-artwork-quick .z4-quick-photo{display:flex;flex-direction:column;align-items:center;gap:10px;padding:22px;border:2px dashed #c9b7a6;border-radius:16px;background:#fffdf9;cursor:pointer;text-align:center}
.z4-artwork-quick .z4-quick-photo-title{font-weight:700;color:var(--ainpa-admin-accent, var(--art-color-accent, #690707));font-size:18px}
.z4-artwork-quick .z4-quick-photo-plus{font-size:44px;line-height:1;color:var(--ainpa-admin-accent, var(--art-color-accent, #690707))}
.z4-artwork-quick .z4-quick-photo-preview{max-width:100%;max-height:260px;border-radius:12px}
.z4-artwork-quick .z4-quick-photo-hint{color:#6b6b6b}
.z4-artwork-quick .z4-quick-photo-note{color:#8a8a8a;font-size:13px}
.z4-artwork-quick .z4-quick-photo input[type=file]{max-width:100%}
.z4-artwork-quick .z4-quick-field{display:grid;gap:6px;align-content:start}
.z4-artwork-quick .z4-quick-field>span{min-height:1.5em}
.z4-artwork-quick .z4-quick-field>span{font-weight:700;font-size:16px}
.z4-artwork-quick .z4-quick-field input,.z4-artwork-quick .z4-quick-field textarea,.z4-artwork-quick .z4-quick-field select{padding:14px;font-size:17px;border:1px solid #d8cfc4;border-radius:12px;width:100%;background:#fff}
.z4-artwork-quick .z4-quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.z4-artwork-quick .z4-quick-limited{display:grid;grid-template-columns:1fr;gap:14px;grid-column:1 / -1}
.z4-artwork-quick .z4-quick-sold{display:grid;grid-template-columns:1fr;gap:14px;grid-column:1 / -1}
.z4-artwork-quick .z4-quick-sold[hidden]{display:none!important}
.z4-artwork-quick .z4-quick-limited[hidden]{display:none!important}
.z4-artwork-quick .z4-quick-confirm{display:flex;gap:12px;align-items:flex-start;padding:16px;border:1px solid #e2d6c6;border-radius:12px;background:#fff8f0;font-size:16px;line-height:1.6}
.z4-artwork-quick .z4-quick-confirm input[type=checkbox]{width:26px!important;height:26px!important;min-width:26px!important;margin-top:2px}
.z4-artwork-quick .z4-quick-confirm-text{display:flex;flex-direction:column;gap:6px;font-size:15px;line-height:1.7}
.z4-artwork-quick .z4-quick-confirm-text b{font-size:16px;color:var(--ainpa-admin-accent, var(--art-color-accent, #690707))}
.z4-artwork-quick .z4-quick-actions{display:flex;gap:12px;flex-wrap:wrap}
.z4-artwork-quick .z4-quick-btn{flex:1;min-width:160px;padding:16px;font-size:18px;font-weight:700;border-radius:12px;border:0;cursor:pointer}
.z4-artwork-quick .z4-quick-btn.is-primary{background:var(--ainpa-admin-accent, var(--art-color-accent, #690707))!important;color:#fff!important}
.z4-artwork-quick .z4-quick-btn.is-secondary{background:#efe7dd!important;color:#4a3b30!important}
.z4-artwork-quick .z4-quick-help{color:#77706a;font-size:13px;line-height:1.5}
.z4-artwork-quick .z4-quick-error{color:#b00020}
.z4-artwork-quick .z4-quick-more{text-align:center;color:#6b6b6b}
@media(max-width:560px){.z4-artwork-quick .z4-quick-grid,.z4-artwork-quick .z4-quick-limited{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var quickForm = document.querySelector('.z4-artwork-quick .z4-quick-form');
    document.querySelectorAll('[data-artwork-locale]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var locale = link.dataset.artworkLocale;
            if (!quickForm || locale === @json($activeArtworkLocale)) { return; }
            var title = quickForm.querySelector('[name="title"]');
            if (!title || !title.value.trim()) { alert('أدخل اسم العمل أولاً قبل تبديل اللغة.'); return; }
            quickForm.querySelector('[name="locale"]').value = locale;
            quickForm.querySelector('[data-artwork-switch-locale]').value = locale;
            quickForm.requestSubmit();
        });
    });
    var sel = document.querySelector('.z4-artwork-quick [data-edition]');
    var box = document.querySelector('.z4-artwork-quick [data-limited]');
    if (sel && box) { var total = box.querySelector('[data-edition-total]'); var sync = function(){ var limited = sel.value === 'limited'; box.hidden = ! limited; if (total) { total.required = limited; } }; sel.addEventListener('change', sync); sync(); }
    var cat = document.querySelector('.z4-artwork-quick [data-category]');
    var catNew = document.querySelector('.z4-artwork-quick [data-category-new]');
    if (cat && catNew) { var syncCat = function(){ var other = cat.value === '__other__'; catNew.hidden = ! other; catNew.required = other; }; cat.addEventListener('change', syncCat); syncCat(); }
    var av = document.querySelector('.z4-artwork-quick [data-availability]');
    var sold = document.querySelector('.z4-artwork-quick [data-sold]');
    if (av && sold) { var syncSold = function(){ sold.hidden = av.value !== 'sold'; }; av.addEventListener('change', syncSold); syncSold(); }
});
</script>
