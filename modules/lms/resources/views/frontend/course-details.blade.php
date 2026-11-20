@php
    use Illuminate\Support\Str;
    use Modules\Lms\Support\VideoEmbedResolver;

    $normalizeCourseList = static function ($value): array {
        $items = is_array($value) ? $value : (is_string($value) && trim($value) !== '' ? [$value] : []);

        $normalized = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $text = trim((string) ($item['title'] ?? $item['text'] ?? $item['value'] ?? reset($item) ?: ''));
            } elseif (is_object($item)) {
                $text = trim((string) ($item->title ?? $item->text ?? $item->value ?? ''));
            } else {
                $text = trim((string) $item);
            }

            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        return array_values(array_unique($normalized));
    };

    $outcomes = $course->outcomeLabels();
    $requirements = $normalizeCourseList($course->requirements ?? []);
    $audience = $normalizeCourseList($course->target_audience ?? []);
    $faqs = array_values(array_filter(
        (array) ($course->faqs ?: []),
        static fn ($faq): bool => is_array($faq) && (filled($faq['question'] ?? null) || filled($faq['answer'] ?? null)),
    ));
    $lessonCount = $lessons->count();
    $currentPrice = $priceSummary['formatted_current'];
    $currency = $priceSummary['currency'];
    $isFree = $priceSummary['pricing_type'] === 'free' || (float) $priceSummary['current'] <= 0;
    $defaultImage = $imageUrl ?: null;
    $previewTitle = $previewLesson?->title ?: $course->title;
    $previewType = $previewLesson?->lesson_type ?: 'video';
    $previewDuration = trim((int) ($previewLesson?->duration_hours ?? 0) ? ((int) $previewLesson->duration_hours).' '.__('lms::ui.course_hours_short').' '.((int) $previewLesson->duration_minutes).' '.__('lms::ui.course_minutes_short') : ((int) ($previewLesson?->duration_minutes ?? 10)).' '.__('lms::ui.course_minutes_short'));
    $previewPlayerUrl = $previewLesson
        ? route('lms.front.courses.player', ['course' => $course, 'lesson' => $previewLesson])
        : route('lms.front.courses.player', $course);
    $aboutDescription = trim(html_entity_decode(
        strip_tags((string) ($course->description ?: $course->short_description ?: __('lms::ui.course_details_soon'))),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    ));
    $promotionalVideoUrl = trim((string) ($course->promo_video_url ?: ($course->promo_video_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($course->promo_video_path) : '')));
    $promotionalEmbedUrl = VideoEmbedResolver::embedUrl($promotionalVideoUrl, autoplay: true);
@endphp

<x-frontend-layout>
    <x-slot name="head">
        <title>{{ $course->title }} - {{ __('lms::ui.course_page_title', ['brand' => $lmsBrand]) }}</title>
        <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
        @foreach (($seoAlternates ?? []) as $alternateLocale => $alternateUrl)
            <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ url($alternateUrl) }}">
        @endforeach
        @if (isset($seoAlternates['en']))
            <link rel="alternate" hreflang="x-default" href="{{ url($seoAlternates['en']) }}">
        @endif
        <meta name="description" content="{{ Str::limit(strip_tags((string) ($course->short_description ?: $course->description)), 160) }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="stylesheet" href="{{ asset('platform/plugins/lms/frontend/css/lms-course-details.css') }}?v=1.0.23-image-fit-20261004">
    </x-slot>

    <main
        class="ainpa-course-detail"
        data-course-detail
        data-coupon-endpoint="{{ $couponEndpoint }}"
        data-login-url="{{ $loginUrl }}"
        data-course-title="{{ $course->title }}"
        data-course-image="{{ $defaultImage }}"
        data-course-price="{{ $isFree ? __('lms::ui.admin_pricing_free') : $currentPrice.' '.$currency }}"
        data-preview-title="{{ $previewTitle }}"
        data-preview-type="{{ $previewType }}"
        data-preview-duration="{{ $previewDuration }}"
        dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}"
    >
        <div class="ainpa-course-shell">
            <nav class="ainpa-course-breadcrumb" aria-label="{{ __('lms::ui.breadcrumb') }}">
                <a href="{{ url('/') }}">{{ __('lms::ui.home') }}</a>
                <span>/</span>
                <a href="{{ route('lms.front.courses') }}">{{ __('lms::ui.courses') }}</a>
                @if($course->category)
                    <span>/</span>
                    <span>{{ $course->category }}</span>
                @endif
                <span>/</span>
                <strong>{{ Str::limit($course->title, 36) }}</strong>
            </nav>

            @if (session('lms_error'))
                <div class="ainpa-course-alert is-error">{{ session('lms_error') }}</div>
            @endif

            @if (session('lms_status'))
                <div class="ainpa-course-alert is-success">{{ session('lms_status') }}</div>
            @endif

            <section class="ainpa-course-hero">
                <div class="ainpa-course-intro">
                    <span class="ainpa-course-pill">{{ $course->category ?: __('lms::ui.course_fallback') }}</span>
                    <h1>{{ $course->title }}</h1>
                    <p>{{ $course->subtitle ?: $course->short_description ?: __('lms::ui.course_learn_practical', ['brand' => $lmsBrand]) }}</p>

                    <div class="ainpa-course-instructor-row">
                        <span class="ainpa-course-avatar">{{ Str::upper(Str::substr($course->instructor_name ?: $lmsBrand, 0, 1)) }}</span>
                        <div>
                            <strong>{{ $course->instructor_name ?: $lmsBrand }}</strong>
                            <small>{{ $course->instructor_title ?: __('lms::ui.course_instructor') }}</small>
                        </div>
                        <div class="ainpa-course-rating" aria-label="{{ __('lms::ui.course-rating') }}">
                            <span>★★★★★</span>
                            <strong>{{ $rating }}</strong>
                            <small>{{ __('lms::ui.course_reviews_count', ['count' => is_numeric($students) ? number_format((int) $students) : $students]) }}</small>
                        </div>
                    </div>

                    <div class="ainpa-course-meta-strip">
                        <div>
                            <span>{{ __('lms::ui.level') }}</span>
                            <strong>{{ __('lms::ui.admin_course_level_'.strtolower($course->level ?: 'beginner')) }}</strong>
                        </div>
                        <div>
                            <span>{{ __('lms::ui.language') }}</span>
                            <strong>{{ __('lms::ui.admin_course_language_'.strtolower($course->language ?: 'english')) }}</strong>
                        </div>
                        <div>
                            <span>{{ __('lms::ui.lessons') }}</span>
                            <strong>{{ $lessonCount }}</strong>
                        </div>
                        <div>
                            <span>{{ __('lms::ui.duration') }}</span>
                            <strong>{{ $totalDuration['label'] ?: __('lms::ui.course_default_duration') }}</strong>
                        </div>
                        <div>
                            <span>{{ __('lms::ui.last-updated') }}</span>
                            <strong>{{ optional($course->updated_at)->translatedFormat('M d, Y') }}</strong>
                        </div>
                    </div>
                </div>

                @if($promotionalVideoUrl)
                    <button type="button" class="ainpa-course-preview-card" data-open-promo aria-label="Watch the promotional video for {{ $course->title }}">
                        @if($defaultImage)
                            <img src="{{ $defaultImage }}" alt="{{ $course->title }}">
                        @else
                            <span class="ainpa-course-preview-fallback">{{ Str::limit($course->title, 2, '') }}</span>
                        @endif
                        <span class="ainpa-course-play" aria-hidden="true">
                            <span></span>
                        </span>
                    </button>
                @else
                    <div class="ainpa-course-preview-card is-static">
                        @if($defaultImage)
                            <img src="{{ $defaultImage }}" alt="{{ $course->title }}">
                        @else
                            <span class="ainpa-course-preview-fallback">{{ Str::limit($course->title, 2, '') }}</span>
                        @endif
                    </div>
                @endif
            </section>

            <div class="ainpa-course-layout">
                <div class="ainpa-course-main">
                    <nav class="ainpa-course-tabs" aria-label="{{ __('lms::ui.course-sections') }}">
                        <button type="button" class="is-active" data-course-tab="about">{{ __('lms::ui.about') }}</button>
                        <button type="button" data-course-tab="curriculum">{{ __('lms::ui.curriculum') }}</button>
                        <button type="button" data-course-tab="instructor">{{ __('lms::ui.instructor') }}</button>
                        <button type="button" data-course-tab="certificate">{{ __('lms::ui.certificate') }}</button>
                        <button type="button" data-course-tab="reviews">{{ __('lms::ui.reviews') }}</button>
                        <button type="button" data-course-tab="faq">{{ __('lms::ui.faq') }}</button>
                    </nav>

                    <section class="ainpa-course-tab-panel is-active" data-course-panel="about">
                        <div class="ainpa-course-card-grid">
                            <article class="ainpa-course-info-card">
                                <h2>{{ __('lms::ui.about-this-course') }}</h2>
                                <p>{{ $aboutDescription }}</p>
                            </article>
                            <article class="ainpa-course-info-card">
                                <h2>{{ __('lms::ui.what-you-will-learn') }}</h2>
                                <ul class="ainpa-course-check-list">
                                    @forelse($outcomes as $outcome)
                                        <li>{{ $outcome }}</li>
                                    @empty
                                        <li>{{ __('lms::ui.build-practical-knowledge-through-structured-lessons') }}</li>
                                        <li>{{ __('lms::ui.apply-course-concepts-in-realistic-projects') }}</li>
                                        <li>{{ __('lms::ui.learn-at-your-own-pace-with-lifetime-access') }}</li>
                                    @endforelse
                                </ul>
                            </article>
                            <article class="ainpa-course-info-card">
                                <h2>{{ __('lms::ui.course-requirements') }}</h2>
                                <ul>
                                    @forelse($requirements as $requirement)
                                        <li>{{ $requirement }}</li>
                                    @empty
                                        <li>{{ __('lms::ui.no-previous-experience-is-required') }}</li>
                                        <li>{{ __('lms::ui.a-laptop-or-tablet-is-recommended') }}</li>
                                    @endforelse
                                </ul>
                            </article>
                            <article class="ainpa-course-info-card">
                                <h2>{{ __('lms::ui.who-this-course-is-for') }}</h2>
                                <ul>
                                    @forelse($audience as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li>{{ __('lms::ui.beginners-who-want-a-structured-learning-path') }}</li>
                                        <li>{{ __('lms::ui.students-and-professionals-improving-their-skills') }}</li>
                                    @endforelse
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="ainpa-course-tab-panel" data-course-panel="curriculum">
                        <div class="ainpa-course-curriculum-head">
                            <h2>{{ __('lms::ui.course-curriculum') }}</h2>
                            <span>{{ __('lms::ui.course_curriculum_summary', ['count' => $lessonCount, 'duration' => $totalDuration['label'] ?: __('lms::ui.course_default_duration')]) }}</span>
                            <button type="button" data-expand-all>{{ __('lms::ui.expand-all') }}</button>
                        </div>
                        <div class="ainpa-course-sections">
                            @forelse($sections as $sectionTitle => $sectionLessons)
                                @php
                                    $sectionId = 'course-section-'.$loop->iteration;
                                    $isFirstSection = $loop->first;
                                @endphp
                                <article class="ainpa-course-section{{ $isFirstSection ? ' is-open' : '' }}">
                                    <button type="button" class="ainpa-course-section-head" data-toggle-section aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $sectionId }}">
                                        <span>{{ $loop->iteration }}. {{ $sectionTitle }}</span>
                                        <strong>{{ __('lms::ui.course_section_lessons', ['count' => $sectionLessons->count()]) }}</strong>
                                    </button>
                                    <div class="ainpa-course-lessons" id="{{ $sectionId }}">
                                        @foreach($sectionLessons as $lesson)
                                            @php
                                                $lessonDuration = trim(((int) $lesson->duration_hours ? ((int) $lesson->duration_hours).' '.__('lms::ui.course_hours_short').' ' : '').((int) $lesson->duration_minutes ? ((int) $lesson->duration_minutes).' '.__('lms::ui.course_minutes_short') : ''));
                                                $isPreviewAvailable = (bool) $lesson->free_preview;
                                                $isLocked = $isEnrolled && $lockedLessonIds->contains((int) $lesson->id);
                                            @endphp
                                            <div class="ainpa-course-lesson-row">
                                                @if($isEnrolled && ! $isLocked)
                                                    <a
                                                        href="{{ route('lms.front.courses.player', ['course' => $course, 'lesson' => $lesson]) }}"
                                                        class="ainpa-course-lesson"
                                                    >
                                                        <span>{{ $loop->parent->iteration }}.{{ $loop->iteration }}</span>
                                                        <strong>{{ $lesson->title }}</strong>
                                                        <small>{{ __('lms::ui.course_lesson_type_'.($lesson->lesson_type ?: 'lesson')) }}</small>
                                                        <b>{{ $lessonDuration ?: __('lms::ui.course_default_lesson_duration') }}</b>
                                                    </a>
                                                @elseif($isEnrolled)
                                                    <span class="ainpa-course-lesson is-locked" title="{{ __('lms::ui.complete-the-previous-lesson-to-unlock-this-lesson') }}" aria-disabled="true">
                                                        <span>{{ $loop->parent->iteration }}.{{ $loop->iteration }}</span>
                                                        <strong>{{ $lesson->title }}</strong>
                                                        <small>{{ __('lms::ui.course_lesson_type_'.($lesson->lesson_type ?: 'lesson')) }}</small>
                                                        <b>{{ $lessonDuration ?: __('lms::ui.course_default_lesson_duration') }}</b>
                                                        <em>{{ __('lms::ui.locked') }}</em>
                                                    </span>
                                                @else
                                                    <button type="button" class="ainpa-course-lesson" data-open-enroll>
                                                        <span>{{ $loop->parent->iteration }}.{{ $loop->iteration }}</span>
                                                        <strong>{{ $lesson->title }}</strong>
                                                        <small>{{ __('lms::ui.course_lesson_type_'.($lesson->lesson_type ?: 'lesson')) }}</small>
                                                        <b>{{ $lessonDuration ?: __('lms::ui.course_default_lesson_duration') }}</b>
                                                    </button>
                                                @endif

                                                @if($isPreviewAvailable)
                                                    <a
                                                        class="ainpa-course-lesson-preview"
                                                        href="{{ route('lms.front.courses.preview', ['course' => $course, 'lesson' => $lesson]) }}"
                                                        aria-label="{{ __('lms::ui.course_preview_aria', ['lesson' => $lesson->title]) }}"
                                                        title="{{ __('lms::ui.preview-lesson') }}"
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Zm9.5 3.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5Z"/></svg>
                                                    </a>
                                                @else
                                                    <span class="ainpa-course-lesson-preview is-disabled" data-tooltip="{{ __('lms::ui.enroll-to-watch') }}" aria-label="{{ __('lms::ui.enroll-to-watch') }}" tabindex="0">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Zm9.5 3.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5Z"/></svg>
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @empty
                                <div class="ainpa-course-empty">{{ __('lms::ui.no-lessons-have-been-published-for-this-course-yet') }}</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="ainpa-course-tab-panel" data-course-panel="instructor">
                        <article class="ainpa-course-info-card">
                            <h2>{{ __('lms::ui.instructor') }}</h2>
                            <div class="ainpa-course-instructor-block">
                                <span class="ainpa-course-avatar is-large">{{ Str::upper(Str::substr($course->instructor_name ?: 'A', 0, 1)) }}</span>
                                <div>
                                    <h3>{{ $course->instructor_name ?: $lmsBrand }}</h3>
                                    <p>{{ $course->instructor_title ?: __('lms::ui.course_instructor') }}</p>
                                    <p>{{ __('lms::ui.course_instructor_leads', ['instructor' => $course->instructor_name ?: __('lms::ui.course_default_instructor')]) }}</p>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="ainpa-course-tab-panel" data-course-panel="certificate">
                        <article class="ainpa-course-info-card">
                            <h2>{{ __('lms::ui.certificate-information') }}</h2>
                            <p>{{ __('lms::ui.students-receive-course-completion-access-after-finishing-th') }}</p>
                            <div class="ainpa-course-certificate">{{ __('lms::ui.certificate-of-completion') }}</div>
                        </article>
                    </section>

                    <section class="ainpa-course-tab-panel" data-course-panel="reviews">
                        <article class="ainpa-course-info-card">
                            <h2>{{ __('lms::ui.reviews') }}</h2>
                            <p>{{ __('lms::ui.course_reviews_intro', ['rating' => $rating]) }}</p>
                        </article>
                    </section>

                    <section class="ainpa-course-tab-panel" data-course-panel="faq">
                        <div class="ainpa-course-faq-list">
                            @forelse($faqs as $faq)
                                <details class="ainpa-course-faq" open>
                                    <summary>{{ $faq['question'] ?? __('lms::ui.auto_a97ea56b0e') }}</summary>
                                    <p>{{ $faq['answer'] ?? '' }}</p>
                                </details>
                            @empty
                                <details class="ainpa-course-faq" open>
                                    <summary>{{ __('lms::ui.how-long-do-i-have-access') }}</summary>
                                    <p>{{ $course->access_duration === 'lifetime' ? __('lms::ui.course_access_lifetime') : __('lms::ui.course_access_duration') }}</p>
                                </details>
                                <details class="ainpa-course-faq">
                                    <summary>{{ __('lms::ui.do-i-get-a-certificate') }}</summary>
                                    <p>{{ __('lms::ui.yes-after-completing-the-course-requirements') }}</p>
                                </details>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="ainpa-course-sidebar" aria-label="{{ __('lms::ui.course-enrollment') }}">
                    <section class="ainpa-course-price-card">
                        <div class="ainpa-course-price-line">
                            <strong>{{ $isFree ? __('lms::ui.admin_pricing_free') : $currentPrice }}</strong>
                            @if(!$isFree)
                                <span>{{ $currency }}</span>
                            @endif
                            @if($priceSummary['formatted_regular'] && $priceSummary['sale'] && $priceSummary['sale'] < $priceSummary['regular'])
                                <del>{{ $priceSummary['formatted_regular'] }}</del>
                            @endif
                            @if($priceSummary['discount'])
                                <em>{{ __('lms::ui.course_discount_off', ['discount' => $priceSummary['discount']]) }}</em>
                            @endif
                        </div>
                        @php($trainingModeValue = $course->training_mode ?: 'online')
                        @if($trainingModeValue !== 'online' || filled($course->in_person_price) || filled($course->online_price))
                            <div class="ainpa-course-training-modes">
                                @if(in_array($trainingModeValue, ['online', 'both'], true) && filled($course->online_price))
                                    <span>{{ __('lms::ui.online-training') }} <b>{{ number_format((float) $course->online_price, 2) }} {{ $course->currency ?: $currency }}</b></span>
                                @endif
                                @if(in_array($trainingModeValue, ['in_person', 'both'], true) && filled($course->in_person_price))
                                    <span>{{ __('lms::ui.in-person-training') }} <b>{{ number_format((float) $course->in_person_price, 2) }} {{ $course->currency ?: $currency }}</b></span>
                                @endif
                            </div>
                        @endif
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

                        @if($isEnrolled)
                            <div class="ainpa-course-status is-enrolled">{{ __('lms::ui.this-course-is-in-your-account') }}</div>
                            <a class="ainpa-course-primary ainpa-course-player-link" href="{{ route('lms.front.courses.player', $course) }}">{{ __('lms::ui.open-course-player') }}</a>
                        @else
                            <div class="ainpa-course-status">{{ __('lms::ui.you-are-not-enrolled-in-this-course') }}</div>
                            <button type="button" class="ainpa-course-primary" data-open-enroll>{{ __('lms::ui.enroll-now') }}</button>
                        @endif
                        <ul class="ainpa-course-benefits">
                            <li>{{ __('lms::ui.30-day-money-back-guarantee') }}</li>
                            <li>{{ __('lms::ui.course_lessons_count', ['count' => $lessonCount]) }}</li>
                            <li>{{ __('lms::ui.course_of_on_demand_content', ['duration' => $totalDuration['label'] ?: __('lms::ui.course_default_duration')]) }}</li>
                            <li>{{ __('lms::ui.full-lifetime-access') }}</li>
                            <li>{{ __('lms::ui.certificate-of-completion') }}</li>
                            <li>{{ __('lms::ui.course_level_inline', ['level' => __('lms::ui.admin_course_level_'.strtolower($course->level ?: 'beginner'))]) }}</li>
                        </ul>
                    </section>

                    <section class="ainpa-course-side-card">
                        <h2>{{ __('lms::ui.instructor-information') }}</h2>
                        <div class="ainpa-course-instructor-mini">
                            <span class="ainpa-course-avatar">{{ Str::upper(Str::substr($course->instructor_name ?: 'A', 0, 1)) }}</span>
                            <div>
                                <strong>{{ $course->instructor_name ?: $lmsBrand }}</strong>
                                <small>{{ $course->instructor_title ?: __('lms::ui.course_instructor') }}</small>
                            </div>
                        </div>
                        <ul>
                            <li>{{ __('lms::ui.course_instructor_rating', ['rating' => $rating]) }}</li>
                            <li>{{ __('lms::ui.instructor_students_count', ['count' => $students]) }}</li>
                            <li>{{ __('lms::ui.course_lessons_in_course', ['count' => $lessonCount]) }}</li>
                        </ul>
                        <a class="ainpa-course-instructor-profile-link" href="{{ route('lms.front.instructors.show', ['instructor' => Str::slug($course->instructor_name ?: $lmsBrand)]) }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-3.5 3.3-5.2 7.5-5.2s6.7 1.7 7.5 5.2"/></svg>
                            <span>{{ __('lms::ui.view-profile') }}</span>
                            <svg class="ainpa-course-instructor-profile-link__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </section>

                </aside>
            </div>
        </div>

        <div class="ainpa-course-modal" data-promo-modal aria-hidden="true">
            <div class="ainpa-course-modal-backdrop" data-close-promo-modal></div>
            <section class="ainpa-course-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="promo-video-title">
                <button type="button" class="ainpa-course-modal-close" data-close-promo-modal aria-label="{{ __('lms::ui.close') }}">×</button>
                <div class="ainpa-course-video-frame">
                    @if($promotionalEmbedUrl)
                        <iframe data-promo-frame data-src="{{ $promotionalEmbedUrl }}" title="{{ __('lms::ui.course_promo_video_title', ['title' => $course->title]) }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    @elseif($promotionalVideoUrl)
                        <video data-promo-video controls playsinline preload="metadata">
                            <source src="{{ $promotionalVideoUrl }}">
                        </video>
                    @else
                        <span aria-hidden="true">▶</span>
                    @endif
                </div>
                <div class="ainpa-course-preview-copy">
                    <h2 id="promo-video-title">{{ $course->title }}</h2>
                    @if($promotionalVideoUrl)
                        <p>{{ __('lms::ui.watch-the-promotional-video-before-enrolling-in-this-course') }}</p>
                    @else
                        <p>{{ __('lms::ui.a-promotional-video-has-not-been-added-for-this-course-yet') }}</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="ainpa-course-modal" data-enroll-modal aria-hidden="true">
            <div class="ainpa-course-modal-backdrop" data-close-modal></div>
            <section class="ainpa-course-enroll-dialog" role="dialog" aria-modal="true" aria-labelledby="enroll-title">
                <button type="button" class="ainpa-course-modal-close" data-close-modal aria-label="{{ __('lms::ui.close') }}">×</button>
                <div class="ainpa-course-enroll-grid">
                    <aside class="ainpa-course-enroll-preview">
                        @if($defaultImage)
                            <img src="{{ $defaultImage }}" alt="{{ $course->title }}">
                        @else
                            <span>{{ Str::limit($course->title, 2, '') }}</span>
                        @endif
                        <h3>{{ $course->title }}</h3>
                        <p>{{ $course->instructor_name ?: $lmsBrand }}</p>
                    </aside>
                    @auth
                        <form class="ainpa-course-enroll-form" data-coupon-form>
                            <span class="ainpa-course-ticket">{{ __('lms::ui.course-code') }}</span>
                            <h2 id="enroll-title">{{ __('lms::ui.enroll-in-this-course') }}</h2>
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
                            <label>
                                <span>{{ __('lms::ui.course-code') }}</span>
                                <input type="text" name="coupon_code" placeholder="C{{ $course->id }}-ABCDEFGH" autocomplete="off" autocapitalize="characters">
                            </label>
                            <button type="submit" class="ainpa-course-primary">{{ __('lms::ui.apply-code') }}</button>
                            <div class="ainpa-course-divider"><span>{{ __('lms::ui.or') }}</span></div>
                            <div class="ainpa-course-coupon-message" data-coupon-message role="status"></div>
                        </form>
                    @else
                        <div class="ainpa-course-enroll-form">
                            <span class="ainpa-course-ticket">{{ __('lms::ui.login-required') }}</span>
                            <h2 id="enroll-title">{{ __('lms::ui.sign-in-first') }}</h2>
                            <p>{{ __('lms::ui.you-must-be-logged-in-before-adding-this-course-to-your-acco') }}</p>
                            <a class="ainpa-course-primary" href="{{ $loginUrl }}">{{ __('lms::ui.sign-in') }}</a>
                            <div class="ainpa-course-account-option">
                                <span>{{ __('lms::ui.course_new_to', ['brand' => $lmsBrand]) }}</span>
                                <a href="{{ route('register') }}">{{ __('lms::ui.create-account') }}</a>
                            </div>
                            <div class="ainpa-course-coupon-message" data-coupon-message role="status"></div>
                        </div>
                    @endauth
                </div>
                <p class="ainpa-course-modal-note">{{ __('lms::ui.each-course-code-works-for-one-assigned-course-and-can-only-') }}</p>
            </section>
        </div>
    </main>

</x-frontend-layout>
