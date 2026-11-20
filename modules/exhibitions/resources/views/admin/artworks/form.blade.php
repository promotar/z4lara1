@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-admin" data-exhibitions-stylesheet>
@endpush
@php
$editing = $artwork !== null;
$value = static fn(string $field, mixed $default = '') => old($field, $artwork?->{$field} ?? $default);
$colors = $value('primary_colors');
if (is_string($colors) && str_starts_with($colors, '[')) $colors = implode(', ', json_decode($colors, true) ?: []);
$keywords = $value('keywords');
if (is_string($keywords) && str_starts_with($keywords, '[')) $keywords = implode(', ', json_decode($keywords, true) ?: []);
@endphp
<x-app-layout>
<main class="z4-exhibitions-admin">
<div class="z4-exhibitions-container z4-exhibitions-form-container">
    <header class="z4-exhibitions-heading">
        <div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_8bd2c5f350') }}</span><h1>{{ $editing ? __('exhibitions::ui.edit_artwork_draft') : __('exhibitions::ui.add_artwork') }}</h1><p>{{ __('exhibitions::ui.auto_9370596dfa') }}</p></div>
        <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.workspace',['area'=>'artworks','tab'=>'drafts']) }}">{{ __('exhibitions::ui.auto_352c531b9b') }}</a>
    </header>
    @if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert"><strong>{{ __('exhibitions::ui.auto_6e2df3e3b8') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="z4-exhibitions-form z4-admin-artwork-form" method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.plugins.exhibitions.artworks.update',$artwork->public_id) : route('admin.plugins.exhibitions.artworks.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="locale" value="{{ $locale ?? app()->getLocale() }}">
        <input type="hidden" name="switch_locale" value="" data-admin-artwork-switch-locale>
        @if(isset($locales) && count($locales) > 1)
            <style>
                .z4-admin-artwork-lang-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px}
                .z4-admin-artwork-lang-tabs a{display:inline-flex;align-items:center;gap:6px;border:1px solid #dfe3ea;border-radius:7px;background:#fff;padding:8px 14px;color:#344054;font-size:12px;font-weight:800;text-decoration:none}
                .z4-admin-artwork-lang-tabs a.is-active{border-color:#7c1d1d;background:#7c1d1d;color:#fff}
                .z4-admin-artwork-lang-tabs a span{font-size:10px;opacity:.8}
                .z4-admin-artwork-lang-note{margin:0 0 14px;border-radius:8px;background:#fff7ed;padding:10px 12px;color:#7c4a03;font-size:13px;line-height:1.6}
            </style>
            <div class="z4-admin-artwork-lang-tabs">
                @foreach($locales as $code => $name)
                    <a href="{{ $editing ? route('admin.plugins.exhibitions.artworks.edit', ['artwork' => $baseArtwork->public_id, 'locale' => $code]) : '#' }}"
                       @if(!$editing) data-admin-artwork-locale="{{ $code }}" @endif
                       class="{{ ($locale ?? 'en') === $code ? 'is-active' : '' }}"
                       @if(($locale ?? 'en') === $code) style="background:#7c1d1d!important;color:#fff!important;border-color:#7c1d1d!important" @endif>
                        {{ $name }}
                        <span>{{ $code === ($defaultLocale ?? 'en') ? '' : (in_array($code, $translatedLocales ?? [], true) ? '?' : '+') }}</span>
                    </a>
                @endforeach
            </div>
            @if(($locale ?? 'en') !== ($defaultLocale ?? 'en'))
                <p class="z4-admin-artwork-lang-note">يتم حفظ الحقول النصية المترجمة فقط، بينما تبقى الأسعار والصور والبيانات المشتركة كما هي.</p>
            @endif
        @endif
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_5eacd7574c') }}</h2><p>{{ __('exhibitions::ui.auto_24491014ec') }}</p></div></div>
            <div class="z4-exhibitions-fields">
                <label>{{ __('exhibitions::ui.auto_a7fce06e9f') }}<select name="artist_user_id"><option value="">{{ __('exhibitions::ui.auto_d88f1912d6') }}</option>@foreach($artists as $artist)<option value="{{ $artist->core_user_id }}" @selected((string)old('artist_user_id',$ownerUserId)===(string)$artist->core_user_id)>{{ $artist->display_name }} — {{ $artist->email }}</option>@endforeach</select></label>
                <label>{{ __('exhibitions::ui.auto_d1e60d289a') }} <b>*</b><input name="title" maxlength="180" value="{{ $value('title') }}" required></label>
                <label>{{ __('exhibitions::ui.auto_f748dac898') }}<input type="number" name="completion_year" min="1000" max="{{ date('Y') }}" value="{{ $value('completion_year') }}"></label>
                <label>{{ __('exhibitions::ui.auto_eb3cb8225e') }}<input name="work_type" maxlength="80" value="{{ $value('work_type') }}" placeholder="{{ __('exhibitions::ui.auto_ec28d827a6') }}"></label>
                <label>{{ __('exhibitions::ui.auto_d15b505cce') }}<input name="collection_name" maxlength="160" value="{{ $value('collection_name') }}"></label>
                <label>{{ __('exhibitions::ui.auto_3adbdb3ac0') }}<input name="category" maxlength="100" value="{{ $value('category') }}"></label>
            </div>
        </section>
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_4d83c2dfc0') }}</h2><p>{{ __('exhibitions::ui.auto_3e787426c7') }}</p></div></div>
            <div class="z4-exhibitions-fields">
                <label>{{ __('exhibitions::ui.auto_226c03c443') }}<select name="edition_type"><option value="original" @selected($value('edition_type','original')==='original')>{{ __('exhibitions::ui.auto_0a52da7a03') }}</option><option value="limited" @selected($value('edition_type')==='limited')>{{ __('exhibitions::ui.auto_4411048ef8') }}</option></select></label>
                <label>{{ __('exhibitions::ui.auto_ac16ed173e') }}<input type="number" min="1" name="edition_number" value="{{ $value('edition_number') }}"></label>
                <label>{{ __('exhibitions::ui.auto_4ce00b2d30') }}<input type="number" min="1" name="edition_total" value="{{ $value('edition_total') }}"></label>
                <label>{{ __('exhibitions::ui.auto_9b7d966268') }}<input name="artistic_style" maxlength="100" value="{{ $value('artistic_style') }}"></label>
                <label>{{ __('exhibitions::ui.auto_c7892ebbb1') }}<input name="subject" maxlength="160" value="{{ $value('subject') }}"></label>
                <label>{{ __('exhibitions::ui.auto_22a70073ad') }}<input name="technique" maxlength="160" value="{{ $value('technique') }}"></label>
                <label>{{ __('exhibitions::ui.auto_d92a8333dd') }}<input name="material" maxlength="160" value="{{ $value('material') }}"></label>
                <label>{{ __('exhibitions::ui.auto_aa0d528ba1') }}<input name="surface" maxlength="120" value="{{ $value('surface') }}"></label>
                <label>{{ __('exhibitions::ui.auto_07bc591ec1') }}<input name="primary_colors" maxlength="500" value="{{ $colors }}" placeholder="{{ __('exhibitions::ui.auto_9f4d953950') }}"></label>
                <label>{{ __('exhibitions::ui.auto_abbd64f40c') }}<select name="orientation"><option value="">{{ __('exhibitions::ui.auto_961f2247a2') }}</option>@foreach(['portrait','landscape','square','panoramic','other'] as $option)<option value="{{ $option }}" @selected($value('orientation')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
                <label>{{ __('exhibitions::ui.auto_32954654ac') }}<input type="number" step="0.01" min="0.01" name="width" value="{{ $value('width') }}"></label>
                <label>{{ __('exhibitions::ui.auto_eec6c4bdbd') }}<input type="number" step="0.01" min="0.01" name="height" value="{{ $value('height') }}"></label>
                <label>{{ __('exhibitions::ui.auto_675056ad14') }}<input type="number" step="0.01" min="0" name="depth" value="{{ $value('depth') }}"></label>
                <label>{{ __('exhibitions::ui.auto_cc13d15630') }}<select name="dimension_unit"><option value="cm" @selected($value('dimension_unit','cm')==='cm')>cm</option><option value="in" @selected($value('dimension_unit')==='in')>in</option></select></label>
                <label>{{ __('exhibitions::ui.auto_8c489d0946') }}<input type="number" step="0.001" min="0" name="weight" value="{{ $value('weight') }}"></label>
                <label>{{ __('exhibitions::ui.auto_f489118ea9') }}<select name="weight_unit"><option value="kg" @selected($value('weight_unit','kg')==='kg')>kg</option><option value="lb" @selected($value('weight_unit')==='lb')>lb</option></select></label>
                <label>{{ __('exhibitions::ui.auto_9e2941b3c8') }}<select name="condition"><option value="">{{ __('exhibitions::ui.auto_961f2247a2') }}</option>@foreach(['new','excellent','good','fair','restored'] as $option)<option value="{{ $option }}" @selected($value('condition')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
                <label>{{ __('exhibitions::ui.auto_f2c6ef3aee') }}<input name="frame_material" maxlength="120" value="{{ $value('frame_material') }}"></label>
                <label class="z4-exhibitions-consent"><input type="hidden" name="is_framed" value="0"><input type="checkbox" name="is_framed" value="1" @checked((bool)$value('is_framed'))> {{ __('exhibitions::ui.auto_6b73ad3725') }}</label>
                <label class="z4-exhibitions-consent"><input type="hidden" name="ready_to_hang" value="0"><input type="checkbox" name="ready_to_hang" value="1" @checked((bool)$value('ready_to_hang'))> {{ __('exhibitions::ui.auto_0a31457556') }}</label>
            </div>
            <div class="z4-exhibitions-fields is-single">
                <label>{{ __('exhibitions::ui.auto_08975685d8') }}</label>
                <label>{{ __('exhibitions::ui.auto_8ee84ea2f1') }}</label>
                <label>{{ __('exhibitions::ui.auto_410dfc8095') }}</label>
                <label>{{ __('exhibitions::ui.auto_7711cada98') }}</label>
                <label>{{ __('exhibitions::ui.auto_181d9360dd') }}</label>
            </div>
        </section>
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>03</span><div><h2>{{ __('exhibitions::ui.auto_c4c4bd5e0d') }}</h2><p>The image is stored by the Exhibitions media pipeline; publication still requires normal review.</p></div></div>
            <div class="z4-exhibitions-fields">
                <label>{{ __('exhibitions::ui.auto_2a5ab1af48') }}<input type="number" step="0.01" min="0.01" name="requested_price" value="{{ $value('requested_price') }}"></label>
                <label>{{ __('exhibitions::ui.auto_386c339d37') }}<select name="currency"><option value="JOD">JOD</option></select></label>
                <label>{{ __('exhibitions::ui.auto_ec83eb0ab2') }}<input type="number" step="0.01" min="0" name="frame_cost" value="{{ $value('frame_cost') }}"></label>
                <label>{{ __('exhibitions::ui.auto_45b4d795bf') }}<input type="number" step="0.01" min="0" name="packaging_cost" value="{{ $value('packaging_cost') }}"></label>
                <label>{{ __('exhibitions::ui.auto_faeaec9eda') }}<select name="availability"><option value="">{{ __('exhibitions::ui.auto_961f2247a2') }}</option>@foreach(['available'=>'Available','made_to_order'=>'Made to order','temporarily_unavailable'=>'Temporarily unavailable'] as $key=>$label)<option value="{{ $key }}" @selected($value('availability')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label>{{ __('exhibitions::ui.auto_1f6ddbb7c6') }}<input type="number" min="0" max="365" name="preparation_days" value="{{ $value('preparation_days') }}"></label>
                <label>{{ __('exhibitions::ui.auto_9ffd022b77') }}<input type="file" name="main_image" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG, or WebP; standard artwork image checks apply.</small></label>
                <label>{{ __('exhibitions::ui.auto_390c85457d') }}<input name="image_alt_text" maxlength="180" value="{{ $value('image_alt_text') }}"></label>
                <label>{{ __('exhibitions::ui.auto_867343577f') }}<input name="keywords" maxlength="1000" value="{{ $keywords }}"></label>
                <label class="z4-exhibitions-field-span">{{ __('exhibitions::ui.auto_a909be1409') }}</label>
                <label class="z4-exhibitions-field-span">{{ __('exhibitions::ui.auto_194b091f8d') }}</label>
            </div>
        </section>
        <div class="z4-exhibitions-actions"><a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.workspace',['area'=>'artworks','tab'=>'drafts']) }}">{{ __('exhibitions::ui.auto_ea4788705e') }}</a><button class="is-primary" type="submit">{{ $editing ? __('exhibitions::ui.save_artwork_draft') : __('exhibitions::ui.create_artwork_draft') }}</button></div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.z4-admin-artwork-form');
    document.querySelectorAll('[data-admin-artwork-locale]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var locale = link.dataset.adminArtworkLocale;
            var current = form ? form.querySelector('[name="locale"]').value : null;
            if (!form || locale === current) { return; }
            var title = form.querySelector('[name="title"]');
            if (!title || !title.value.trim()) { alert('أدخل اسم العمل أولاً قبل تبديل اللغة.'); return; }
            form.querySelector('[name="locale"]').value = locale;
            form.querySelector('[data-admin-artwork-switch-locale]').value = locale;
            form.requestSubmit();
        });
    });
});
</script>
</main>
</x-app-layout>
