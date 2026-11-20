@php use Illuminate\Support\Str; @endphp
<x-frontend-layout>
    <x-slot name="head">
        <title>{{ $course->title }} - {{ config('app.name') }}</title>
        <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
        @foreach (($seoAlternates ?? []) as $alternateLocale => $alternateUrl)
            <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ url($alternateUrl) }}">
        @endforeach
        @if (isset($seoAlternates['en']))
            <link rel="alternate" hreflang="x-default" href="{{ url($seoAlternates['en']) }}">
        @endif
        <meta name="description" content="{{ Str::limit(strip_tags((string) ($course->short_description ?: $course->description)), 160) }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ asset('platform/plugins/lms/frontend/css/lms-course-details.css') }}?v=1.3.3-image-fit-20261004">
    </x-slot>

    <main
        class="ainpa-course-detail lms-course-template-page"
        data-course-detail
        data-coupon-endpoint="{{ $couponEndpoint }}"
        data-login-url="{{ $loginUrl }}"
        data-course-title="{{ $course->title }}"
        data-course-image="{{ $imageUrl }}"
        data-course-price="{{ $priceSummary['formatted_current'] }} {{ $priceSummary['currency'] }}"
        data-price-online="{{ (float) $course->online_price > 0 ? number_format((float) $course->online_price, 2).' '.strtoupper((string) ($course->currency ?: 'USD')) : '' }}"
        data-price-inperson="{{ (float) $course->in_person_price > 0 ? number_format((float) $course->in_person_price, 2).' '.strtoupper((string) ($course->currency ?: 'USD')) : '' }}"
        dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}"
    >
        @if(session('lms_error'))<div class="ainpa-course-shell"><div class="ainpa-course-alert is-error">{{ session('lms_error') }}</div></div>@endif
        @if(session('lms_status'))<div class="ainpa-course-shell"><div class="ainpa-course-alert is-success">{{ session('lms_status') }}</div></div>@endif

        @php($switchMode = in_array($course->training_mode, ['online', 'in_person', 'both'], true) ? $course->training_mode : 'online')
        @if($isEnrolled && $switchMode !== 'online')
            <div class="ainpa-course-shell" style="margin:18px auto;max-width:1100px">
                <form data-training-mode-form action="{{ route('lms.front.courses.training-mode', $course) }}" style="align-items:center;background:#faf9ff;border:1px solid #e3dcff;border-radius:12px;display:flex;flex-wrap:wrap;gap:10px;padding:12px 16px">
                    @csrf
                    <strong style="color:#6d28d9;font-size:14px">{{ __('lms::ui.your-training-type') }}</strong>
                    <select name="training_mode" style="border:1px solid #d8d0f5;border-radius:8px;font-size:13px;padding:7px 10px">
                        @if($switchMode === 'both' || $switchMode === 'online')<option value="online" @selected(($enrollmentTrainingMode ?? 'online') === 'online')>{{ __('lms::ui.online') }}</option>@endif
                        @if($switchMode === 'both' || $switchMode === 'in_person')<option value="in_person" @selected(($enrollmentTrainingMode ?? '') === 'in_person')>{{ __('lms::ui.in-person') }}</option>@endif
                    </select>
                    <button type="submit" style="background:#6d28d9;border:0;border-radius:8px;color:#fff;cursor:pointer;font-size:13px;font-weight:700;padding:8px 14px">{{ __('lms::ui.save') }}</button>
                    <span data-training-mode-message style="color:#15803d;font-size:13px"></span>
                </form>
            </div>
        @endif

        {!! $renderedTemplate !!}

        @if($promotionalVideoUrl)
            <div class="ainpa-course-modal" data-promo-modal aria-hidden="true">
                <div class="ainpa-course-modal-backdrop" data-close-promo-modal></div>
                <section class="ainpa-course-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="promo-video-title">
                    <button type="button" class="ainpa-course-modal-close" data-close-promo-modal aria-label="{{ __('lms::ui.close') }}">×</button>
                    <div class="ainpa-course-video-frame">
                        @if($promotionalEmbedUrl)
                            <iframe data-promo-frame data-src="{{ $promotionalEmbedUrl }}" title="{{ __('lms::ui.course_promo_video_title', ['title' => $course->title]) }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                        @else
                            <video data-promo-video controls playsinline preload="metadata"><source src="{{ $promotionalVideoUrl }}"></video>
                        @endif
                    </div>
                    <div class="ainpa-course-preview-copy">
                        <h2 id="promo-video-title">{{ $course->title }}</h2>
                        <p>{{ __('lms::ui.watch-the-promotional-video-before-enrolling-in-this-course') }}</p>
                    </div>
                </section>
            </div>
        @endif

        <div class="ainpa-course-modal" id="lms-enroll" data-enroll-modal aria-hidden="true">
            <div class="ainpa-course-modal-backdrop" data-close-modal></div>
            <section class="ainpa-course-enroll-dialog" role="dialog" aria-modal="true" aria-labelledby="enroll-title">
                <button type="button" class="ainpa-course-modal-close" data-close-modal aria-label="{{ __('lms::ui.close') }}">×</button>
                <div class="ainpa-course-enroll-grid">
                    <aside class="ainpa-course-enroll-preview">
                        @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $course->title }}">@else<span>{{ Str::limit($course->title,2,'') }}</span>@endif
                        <h3>{{ $course->title }}</h3><p>{{ $course->instructor_name ?: config('app.name') }}</p>
                    </aside>
                    @auth
                        <form class="ainpa-course-enroll-form" data-coupon-form>
                            <span class="ainpa-course-ticket">{{ __('lms::ui.course-code') }}</span><h2 id="enroll-title">{{ __('lms::ui.enroll-in-this-course') }}</h2>
                            <p>{{ __('lms::ui.enter-the-course-code-you-received-each-code-can-be-used-onc') }}</p>
                            @php($enrollTrainingMode = in_array($course->training_mode, ['online', 'in_person', 'both'], true) ? $course->training_mode : 'online')
                            @if($enrollTrainingMode !== 'online')
                                <fieldset data-training-choice style="border:1px solid #e3dcff;border-radius:10px;margin:0 0 12px;padding:10px 12px;background:#faf9ff">
                                    <legend style="color:#6d28d9;font-size:12px;font-weight:800;padding:0 6px">{{ __('lms::ui.training-type') }}</legend>
                                    @if($enrollTrainingMode === 'both')
                                        <label style="align-items:center;display:flex;gap:8px;font-size:13px;margin:4px 0"><input type="radio" name="enrollment_training_mode" value="online" checked><span>{{ __('lms::ui.online') }} @if((float) $course->online_price > 0) — {{ number_format((float) $course->online_price, 2) }} {{ $course->currency ?: 'JOD' }}@endif</span></label>
                                        <label style="align-items:center;display:flex;gap:8px;font-size:13px;margin:4px 0"><input type="radio" name="enrollment_training_mode" value="in_person"><span>{{ __('lms::ui.in-person') }} @if((float) $course->in_person_price > 0) — {{ number_format((float) $course->in_person_price, 2) }} {{ $course->currency ?: 'JOD' }}@endif</span></label>
                                    @else
                                        <label style="align-items:center;display:flex;gap:8px;font-size:13px;margin:4px 0"><input type="radio" name="enrollment_training_mode" value="in_person" checked><span>{{ __('lms::ui.in-person') }} @if((float) $course->in_person_price > 0) — {{ number_format((float) $course->in_person_price, 2) }} {{ $course->currency ?: 'JOD' }}@endif</span></label>
                                    @endif
                                </fieldset>
                            @endif
                            <label><span>{{ __('lms::ui.course-code') }}</span><input type="text" name="coupon_code" placeholder="C{{ $course->id }}-ABCDEFGH" autocomplete="off" autocapitalize="characters"></label>
                            <button type="submit" class="ainpa-course-primary">{{ __('lms::ui.apply-code') }}</button>
                            <div class="ainpa-course-coupon-message" data-coupon-message role="status"></div>
                        </form>
                    @else
                        <div class="ainpa-course-enroll-form"><span class="ainpa-course-ticket">{{ __('lms::ui.login-required') }}</span><h2 id="enroll-title">{{ __('lms::ui.sign-in-first') }}</h2><p>{{ __('lms::ui.you-must-be-logged-in-before-adding-this-course-to-your-acco') }}</p><a class="ainpa-course-primary" href="{{ $loginUrl }}">{{ __('lms::ui.sign-in') }}</a></div>
                    @endauth
                </div>
            </section>
        </div>
    </main>
</x-frontend-layout>
