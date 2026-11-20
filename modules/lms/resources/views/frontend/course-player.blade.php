@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\URL;
    use Modules\Lms\Support\VideoEmbedResolver;

    $courseImage = $imageUrl ?: null;
    $lessonDuration = trim(((int) $currentLesson->duration_hours ? ((int) $currentLesson->duration_hours).'h ' : '').((int) $currentLesson->duration_minutes ? ((int) $currentLesson->duration_minutes).'m' : ''));
    $lessonDuration = $lessonDuration ?: '10m';
    $lessonNumber = $lessonIndex + 1;
    $totalLessons = $lessons->count();
    $learnItems = $course->outcomeLabels();
    $learnItems = $learnItems ?: [
        'Core concepts and practical applications',
        'Tools, materials, and workflow essentials',
        'How to complete this lesson with confidence',
        'Next steps for continuing the course',
    ];
    $completedSeedArray = $completedSeed->map(fn ($id) => (int) $id)->values()->all();
    $isCurrentComplete = in_array((int) $currentLesson->id, $completedSeedArray, true);
    $studentName = trim((string) ($studentName ?? '')) ?: 'Student';
    $platformValues = [];
    try {
        $platformValues = app(\App\Platform\Core\Services\SettingsRepository::class)->values();
        $supportEmail = trim((string) ($platformValues['contact.support_email'] ?? ''));
    } catch (\Throwable $exception) {
        $supportEmail = '';
    }
    $supportEmail = $supportEmail ?: trim((string) config('mail.from.address', 'admin@z4rank.com'));
    $currentSectionTitle = trim((string) ($currentLesson->module_title ?? ''));
    $hasMeaningfulCurrentSection = $currentSectionTitle !== ''
        && ! preg_match('/^(chapter|section|module)\\s*\\d+$/i', $currentSectionTitle);
    $completionRequirement = (string) ($currentLesson->completion_requirement ?: 'manual');
    $allowsManualCompletion = $completionRequirement === 'manual';
    $formatResourceSize = static function ($size): string {
        $value = trim((string) $size);

        if ($value === '') {
            return 'File size unavailable';
        }

        if (! is_numeric($value)) {
            return $value;
        }

        $bytes = max(0, (float) $value);
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return number_format($bytes, $unit === 0 ? 0 : 1).' '.$units[$unit];
    };
    $siteName = $lmsBrand;
    $configuredLogo = trim((string) ($platformValues['general.site_logo'] ?? ''));
    $logoUrl = $configuredLogo === ''
        ? null
        : (Str::startsWith($configuredLogo, ['http://', 'https://', '//'])
            ? $configuredLogo
            : url('/'.ltrim($configuredLogo, '/')));
    $rawVideoUrl = trim((string) $currentLesson->video_url);
    $storedVideoPath = trim((string) $currentLesson->video_path);
    $hasStoredVideo = $storedVideoPath !== '';
    $videoProvider = 'none';
    $videoToken = '';
    $videoSource = '';
    $localVideo = $storedVideoPath !== '';

    if ($hasStoredVideo) {
        $videoProvider = 'protected_file';
        $videoSource = URL::temporarySignedRoute(
            'lms.front.courses.player.video',
            now()->addHours(8),
            ['course' => $course, 'lesson' => $currentLesson],
            absolute: false,
        );
    } elseif ($rawVideoUrl !== '') {
        $parsedVideoUrl = parse_url($rawVideoUrl) ?: [];
        $resolvedEmbed = VideoEmbedResolver::resolve($rawVideoUrl, autoplay: true, controlled: true);

        if (($resolvedEmbed['provider'] ?? null) === 'youtube') {
            $videoProvider = 'provider_a';
            $videoToken = $resolvedEmbed['token'];
        } elseif (($resolvedEmbed['provider'] ?? null) === 'vimeo') {
            $videoProvider = 'provider_b';
            $videoToken = $resolvedEmbed['token'];
        } elseif (($resolvedEmbed['provider'] ?? null) === 'peertube') {
            $videoProvider = 'peertube';
            $videoToken = $resolvedEmbed['token'];
            $videoSource = $resolvedEmbed['embed_url'];
        } elseif (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $rawVideoUrl)) {
            $localVideo = $localVideo || Str::startsWith((string) ($parsedVideoUrl['path'] ?? ''), '/storage/');
            $videoProvider = $localVideo && ! ($isPreviewMode ?? false) ? 'protected_file' : 'direct_file';
            $videoSource = $rawVideoUrl;
        } else {
            $videoProvider = 'remote_embed';
            $videoSource = $rawVideoUrl;
        }
    }

@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $currentLesson->title }} - {{ $course->title }}</title>
    <meta name="description" content="{{ Str::limit(strip_tags((string) ($currentLesson->description ?: $currentLesson->content ?: $course->short_description)), 160) }}">
<link rel="stylesheet" href="{{ asset('platform/plugins/lms/frontend/css/lms-course-player.css') }}?v=1.5.11">
</head>
<body>
    <main
        class="ainpa-player"
        data-lms-player
        data-course-id="{{ $course->id }}"
        data-lesson-id="{{ $currentLesson->id }}"
        data-video-provider="{{ $videoProvider }}"
        data-video-token="{{ $videoToken }}"
        data-video-source="{{ $videoSource }}"
        data-poster="{{ $courseImage }}"
        data-completed-seed='@json($completedSeedArray)'
        data-initial-progress="{{ $progressPercent }}"
        data-progress-endpoint="{{ ($isPreviewMode ?? false) ? '' : route('lms.front.courses.player.progress', ['course' => $course, 'lesson' => $currentLesson]) }}"
        data-preview-mode="{{ ($isPreviewMode ?? false) ? 'true' : 'false' }}"
        data-completion-requirement="{{ $completionRequirement }}"
        data-csrf-token="{{ csrf_token() }}"
        data-notifications-endpoint="{{ route('lms.front.notifications.index', [], false) }}"
    >
        <aside class="ainpa-player-sidebar" data-player-sidebar>
            <div class="ainpa-player-brand">
                <a href="{{ url('/') }}" aria-label="{{ $siteName }} home">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}">
                    @else
                        <span class="ainpa-player-brand-name">{{ $siteName }}</span>
                    @endif
                </a>
            </div>

            <div class="ainpa-player-course-summary">
                <a class="ainpa-player-back" href="{{ ($isPreviewMode ?? false) ? route('lms.front.courses.show', $course) : route('lms.front.student.courses') }}">{{ ($isPreviewMode ?? false) ? __('lms::ui.player_back_to_course') : __('lms::ui.player_back_to_courses') }}</a>
                <div class="ainpa-player-course-card">
                    @if($courseImage)
                        <img src="{{ $courseImage }}" alt="{{ $course->title }}">
                    @else
                        <span>{{ Str::limit($course->title, 2, '') }}</span>
                    @endif
                    <div>
                        <h1 dir="auto">{{ $course->title }}</h1>
                        <p>{{ $course->subtitle ?: $course->short_description ?: 'Continue your course lessons.' }}</p>
                        <strong><span data-progress-label>{{ $progressPercent }}</span>{{ __('lms::ui.complete') }}</strong>
                        <div class="ainpa-player-progress-mini"><span data-progress-mini style="width: {{ $progressPercent }}%"></span></div>
                    </div>
                </div>
            </div>

            <nav class="ainpa-player-side-tabs" aria-label="{{ __('lms::ui.course-side-tabs') }}">
                <button type="button" class="is-active" data-side-tab="curriculum">{{ __('lms::ui.curriculum') }}</button>
                <button type="button" data-side-tab="notes">{{ __('lms::ui.notes') }}</button>
            </nav>

            <section class="ainpa-player-side-panel is-active" data-side-panel="curriculum">
                @foreach($sections as $sectionTitle => $sectionLessons)
                    @php
                        $sectionDisplayTitle = trim((string) $sectionTitle);
                        $sectionDisplayTitle = $sectionDisplayTitle !== '' ? $sectionDisplayTitle : 'Unit '.$loop->iteration;
                        $sectionContainsCurrent = $sectionLessons->contains(
                            fn ($lesson) => (int) $lesson->id === (int) $currentLesson->id
                        );
                    @endphp
                    <article class="ainpa-player-section {{ $sectionContainsCurrent ? 'is-open' : '' }}" data-player-section>
                        <button type="button" class="ainpa-player-section-head" data-toggle-player-section aria-expanded="{{ $sectionContainsCurrent ? 'true' : 'false' }}">
                            <span dir="auto">{{ $loop->iteration }}. {{ $sectionDisplayTitle }}</span>
                            <small>{{ __('lms::ui.player_lessons_count', ['count' => $sectionLessons->count()]) }}</small>
                        </button>
                        <div class="ainpa-player-lesson-list">
                            @foreach($sectionLessons as $lesson)
                                @php
                                    $itemDuration = trim(((int) $lesson->duration_hours ? ((int) $lesson->duration_hours).'h ' : '').((int) $lesson->duration_minutes ? ((int) $lesson->duration_minutes).'m' : ''));
                                    $itemDuration = $itemDuration ?: '10m';
                                    $isCurrent = (int) $lesson->id === (int) $currentLesson->id;
                                    $isSeedComplete = in_array((int) $lesson->id, $completedSeedArray, true);
                                @endphp
                                @php
                                    $isLocked = $lockedLessonIds->contains((int) $lesson->id);
                                @endphp
                                <a
                                    href="{{ $isLocked ? '#' : (($isPreviewMode ?? false) ? route('lms.front.courses.preview', ['course' => $course, 'lesson' => $lesson]) : route('lms.front.courses.player', ['course' => $course, 'lesson' => $lesson])) }}"
                                    class="ainpa-player-lesson {{ $isCurrent ? 'is-active' : '' }} {{ $isSeedComplete ? 'is-complete' : '' }} {{ $isLocked ? 'is-locked' : '' }}"
                                    data-lesson-row data-lesson-id="{{ $lesson->id }}"
                                    aria-disabled="{{ $isLocked ? 'true' : 'false' }}"
                                >
                                    <span class="ainpa-player-lesson-icon"></span>
                                    <strong>{{ $loop->parent->iteration }}.{{ $loop->iteration }}</strong>
                                    <b dir="auto" title="{{ $lesson->title }}">{{ $lesson->title }}</b>
                                    <span class="ainpa-player-lesson-meta">
                                        @if($isLocked)
                                            <em class="is-locked" data-row-state title="{{ __('lms::ui.locked') }}">{{ __('lms::ui.locked') }}</em>
                                        @else
                                            <small>{{ $itemDuration }}</small>
                                            <em data-row-state title="{{ $isSeedComplete ? 'Completed' : '' }}"></em>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </article>
                @endforeach

                <div class="ainpa-player-help">
                    <strong>{{ __('lms::ui.need-help') }}</strong>
                    <p>{{ __('lms::ui.if-you-have-any-questions-please-email-our') }} <a href="mailto:{{ $supportEmail }}">{{ __('lms::ui.support-team') }}</a>.</p>
                </div>
            </section>

            <section class="ainpa-player-side-panel" data-side-panel="notes">
                <label class="ainpa-player-notes">
                    <span>{{ __('lms::ui.private-lesson-notes') }}</span>
                    <textarea data-notes-input rows="12" placeholder="{{ __('lms::ui.write-your-notes-for-this-lesson') }}"></textarea>
                </label>
                <p class="ainpa-player-note-status" data-notes-status>{{ __('lms::ui.notes-are-saved-in-this-browser') }}</p>
            </section>

        </aside>

        <section class="ainpa-player-main">
            <header class="ainpa-player-topbar">
                <div class="ainpa-player-top-left">
                    <button type="button" class="ainpa-player-menu-toggle" data-toggle-sidebar aria-label="{{ __('lms::ui.toggle-lessons-menu') }}" aria-expanded="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16"></path>
                        </svg>
                    </button>
                    <a href="{{ ($isPreviewMode ?? false) ? route('lms.front.courses.show', $course) : route('lms.front.student.courses') }}">{{ ($isPreviewMode ?? false) ? __('lms::ui.course-details') : __('lms::ui.player_my_courses') }}</a>
                </div>
                <div class="ainpa-player-progress-top">
                    <span>{{ __('lms::ui.course-progress') }}</span>
                    <div><i data-progress-top style="width: {{ $progressPercent }}%"></i></div>
                    <strong><span data-progress-label>{{ $progressPercent }}</span>{{ __('lms::ui.complete') }}</strong>
                </div>
                <div class="ainpa-player-user">
                    <button type="button" class="ainpa-player-theme-toggle" data-theme-toggle aria-label="{{ __('lms::ui.switch-to-dark-mode') }}" title="{{ __('lms::ui.switch-to-dark-mode') }}">
                        <svg class="ainpa-theme-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.3 15.4A8.4 8.4 0 0 1 8.6 3.7 8.5 8.5 0 1 0 20.3 15.4Z"></path></svg>
                        <svg class="ainpa-theme-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"></path></svg>
                    </button>
                    <div class="ainpa-player-notifications" data-notifications>
                        <button type="button" class="ainpa-player-notification-toggle" data-notification-button aria-label="{{ __('lms::ui.notifications') }}" aria-expanded="false" aria-controls="lms-player-notifications">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"></path></svg>
                            <b data-notification-count hidden>0</b>
                        </button>
                        <section id="lms-player-notifications" class="ainpa-player-notification-panel" data-notification-panel hidden>
                            <header><div><strong>{{ __('lms::ui.notifications') }}</strong><small data-notification-summary>{{ __('lms::ui.loading') }}</small></div><button type="button" data-notifications-read-all>{{ __('lms::ui.mark-all-read') }}</button></header>
                            <div data-notification-list><p>{{ __('lms::ui.loading-notifications') }}</p></div>
                        </section>
                    </div>
                    <details class="ainpa-player-account">
                        <summary aria-label="{{ __('lms::ui.open-account-menu') }}">
                            <span class="ainpa-player-account-avatar">{{ Str::upper(Str::substr($studentName, 0, 1)) }}</span>
                            <strong dir="auto">{{ $studentName }}</strong>
                        </summary>
                        <div class="ainpa-player-account-menu">
                            @if($isPreviewMode ?? false)
                                <a href="{{ route('lms.front.courses.show', $course) }}">{{ __('lms::ui.course-details') }}</a>
                            @else
                                <a href="{{ route('lms.front.student.overview') }}">{{ __('lms::ui.student-dashboard') }}</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">{{ __('lms::ui.sign-out') }}</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            <div class="ainpa-player-content">
                <div class="ainpa-player-content-head">
                    <div class="ainpa-player-breadcrumb">
                        <a href="{{ route('lms.front.courses.show', $course) }}" dir="auto">{{ $course->title }}</a>
                        @if($hasMeaningfulCurrentSection)
                        <span></span>
                        <a href="{{ ($isPreviewMode ?? false) ? route('lms.front.courses.preview', ['course' => $course, 'lesson' => $currentLesson]) : route('lms.front.courses.player', $course) }}" dir="auto">{{ $currentSectionTitle }}</a>
                        @endif
                        <span></span>
                        <strong dir="auto" title="{{ $currentLesson->title }}">{{ $lessonNumber }}. {{ $currentLesson->title }}</strong>
                    </div>

                    <div class="ainpa-player-actions">
                        <button type="button" class="ainpa-player-icon-btn" data-bookmark aria-label="{{ __('lms::ui.bookmark-lesson') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6.5 4.75A2.25 2.25 0 0 1 8.75 2.5h6.5a2.25 2.25 0 0 1 2.25 2.25v16.1l-5.5-3.45-5.5 3.45V4.75Z"></path>
                            </svg>
                        </button>
                        <button type="button" class="ainpa-player-icon-btn" data-fullscreen aria-label="{{ __('lms::ui.fullscreen-player') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8.5 3.5h-5v5M15.5 3.5h5v5M20.5 15.5v5h-5M3.5 15.5v5h5"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                @if($currentLesson->lesson_type === 'video')
                <section class="ainpa-player-video" data-video-shell>
                    @if($courseImage)
                        <img src="{{ $courseImage }}" alt="{{ $currentLesson->title }}" data-video-poster>
                    @else
                        <div class="ainpa-player-video-fallback" data-video-poster>{{ Str::limit($course->title, 2, '') }}</div>
                    @endif
                    <button type="button" class="ainpa-player-play" data-video-play aria-label="{{ __('lms::ui.play-lesson-video') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 5.5v13l10-6.5-10-6.5Z"></path>
                        </svg>
                    </button>
                    <div class="ainpa-player-video-mount" data-video-mount></div>
                    <div class="ainpa-player-controls">
                        <button type="button" data-video-play-small aria-label="{{ __('lms::ui.play-or-pause') }}">
                            <svg class="ainpa-player-control-play-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 5.5v13l10-6.5-10-6.5Z"></path>
                            </svg>
                            <svg class="ainpa-player-control-pause-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 5h2.8v14H8V5Zm5.2 0H16v14h-2.8V5Z"></path>
                            </svg>
                        </button>
                        <button type="button" data-volume-toggle aria-label="{{ __('lms::ui.toggle-volume') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 9.5v5h3.4L12 18.2V5.8L7.4 9.5H4Z"></path>
                                <path d="M15.5 8.25a5 5 0 0 1 0 7.5M17.9 5.8a8.5 8.5 0 0 1 0 12.4"></path>
                            </svg>
                        </button>
                        <time><span data-current-time>00:00</span> / {{ $lessonDuration }}</time>
                        <div class="ainpa-player-timeline"><span data-watch-bar style="width: 28%"></span><button type="button" aria-label="{{ __('lms::ui.seek') }}"></button></div>
                        <button type="button" data-speed-toggle>{{ __('lms::ui.1-25x') }}</button>
                        <button type="button" data-caption-toggle>{{ __('lms::ui.cc') }}</button>
                        <button type="button" data-settings-toggle aria-label="{{ __('lms::ui.settings') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 8.25A3.75 3.75 0 1 1 12 15.75 3.75 3.75 0 0 1 12 8.25Z"></path>
                                <path d="M19.2 13.3a7.75 7.75 0 0 0 .05-1.3l2-1.55-2-3.45-2.45 1a8 8 0 0 0-1.15-.65L15.3 4.7h-4l-.35 2.65c-.4.18-.78.4-1.15.65l-2.45-1-2 3.45 2 1.55a7.75 7.75 0 0 0 .05 1.3l-2.05 1.6 2 3.45 2.5-1.05c.34.24.7.44 1.1.6l.35 2.7h4l.35-2.7c.4-.16.76-.36 1.1-.6l2.5 1.05 2-3.45-2.05-1.6Z"></path>
                            </svg>
                        </button>
                        <button type="button" data-fullscreen aria-label="{{ __('lms::ui.fullscreen') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8.5 3.5h-5v5M15.5 3.5h5v5M20.5 15.5v5h-5M3.5 15.5v5h5"></path>
                            </svg>
                        </button>
                    </div>
                </section>
                @elseif($currentLesson->lesson_type === 'text')
                <section class="ainpa-player-content-shell ainpa-player-text-content" aria-label="{{ __('lms::ui.text-lesson-content') }}">
                    <div>{!! $currentLesson->content !!}</div>
                </section>
                @elseif($currentLesson->lesson_type === 'pdf')
                    @php
                        $pdfResource = collect($resources)->first(
                            fn ($item) => strtolower((string) ($item['type'] ?? '')) === 'pdf'
                        ) ?: collect($resources)->first();
                    @endphp
                    <section class="ainpa-player-content-shell ainpa-player-pdf-content" aria-label="{{ __('lms::ui.pdf-lesson-content') }}">
                        @if($pdfResource && ($pdfResource['url'] ?? null))
                            <iframe src="{{ $pdfResource['url'] }}#toolbar=1&navpanes=0" title="{{ $currentLesson->title }} PDF"></iframe>
                            <a class="ainpa-player-btn" href="{{ $pdfResource['url'] }}" target="_blank" rel="noopener">{{ __('lms::ui.open-pdf-in-a-new-tab') }}</a>
                        @else
                            <div class="ainpa-player-content-empty"><strong>{{ __('lms::ui.no-pdf-attached') }}</strong><p>{{ __('lms::ui.the-instructor-has-not-attached-a-pdf-to-this-lesson-yet') }}</p></div>
                        @endif
                    </section>
                @else
                <section class="ainpa-player-content-shell"><div class="ainpa-player-content-empty"><strong>{{ __('lms::ui.lesson-content-is-not-available') }}</strong></div></section>
                @endif

                <div class="ainpa-player-lesson-layout">
                    <article class="ainpa-player-lesson-copy">
                        <h2 dir="auto">{{ $lessonNumber }}. {{ $currentLesson->title }}</h2>
                        <p dir="auto">{{ $currentLesson->description ?: Str::limit(strip_tags((string) ($currentLesson->content ?: $course->short_description)), 220) }}</p>
                        @if($currentLesson->lesson_type === 'video')<hr><h3>{{ __('lms::ui.in-this-lesson-you-will-learn') }}</h3><ul>@foreach(array_slice($learnItems, 0, 4) as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
                    </article>

                    @if($resources)
                    <aside class="ainpa-player-resources-card">
                        <h2>{{ __('lms::ui.lesson-resources') }}</h2>
                        <div class="ainpa-player-resource-list">
                            @foreach($resources as $resource)
                                @php
                                    $resourceType = strtoupper(trim((string) ($resource['type'] ?? 'PDF'))) ?: 'FILE';
                                    $resourceSize = $formatResourceSize($resource['size'] ?? '');
                                    $resourceUrl = $resource['url'] ?? '#';
                                @endphp
                                <div class="ainpa-player-resource">
                                    <span>{{ $resource['type'] ?: 'PDF' }}</span>
                                    <div class="ainpa-player-resource-info">
                                        <strong dir="auto" title="{{ $resource['title'] }}">{{ $resource['title'] }}</strong>
                                        <small>{{ $resourceType }} &middot; {{ $resourceSize }}</small>
                                    </div>
                                    @if($resourceUrl !== '#')
                                        <a href="{{ $resourceUrl }}" class="ainpa-player-resource-action" download>Download {{ $resourceType === 'PDF' ? 'PDF' : 'File' }}</a>
                                    @else
                                        <span class="ainpa-player-resource-action is-disabled">{{ __('lms::ui.unavailable') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </aside>
                    @endif
                </div>

                <nav class="ainpa-player-bottom-nav" aria-label="{{ __('lms::ui.lesson-navigation') }}">
                    @if($previousLesson)
                        <a href="{{ route('lms.front.courses.player', ['course' => $course, 'lesson' => $previousLesson]) }}" class="ainpa-player-prev">
                            <span>{{ __('lms::ui.previous-lesson') }}</span>
                            <strong>{{ $previousLesson->title }}</strong>
                        </a>
                    @else
                        <span class="ainpa-player-prev is-disabled">
                            <span>{{ __('lms::ui.previous-lesson') }}</span>
                            <strong>{{ __('lms::ui.no-previous-lesson') }}</strong>
                        </span>
                    @endif

                    <div>{{ __('lms::ui.player_lesson_of', ['current' => $lessonNumber, 'total' => $totalLessons]) }}</div>

                    @if($isPreviewMode ?? false)
                        <span class="ainpa-player-next is-disabled">
                            <span>{{ __('lms::ui.course-preview') }}</span>
                            <strong>{{ __('lms::ui.enroll-to-continue-learning') }}</strong>
                        </span>
                    @elseif(! $isCurrentComplete && $allowsManualCompletion)
                        <button
                            type="button"
                            class="ainpa-player-next ainpa-player-complete-next"
                            data-complete-and-next
                            data-next-url="{{ $upcomingLesson ? route('lms.front.courses.player', ['course' => $course, 'lesson' => $upcomingLesson]) : '' }}"
                        >
                            <span data-complete-next-label>{{ $upcomingLesson ? __('lms::ui.player_complete_continue') : __('lms::ui.player_finish_course') }}</span>
                            <strong dir="auto">{{ $upcomingLesson ? $upcomingLesson->title : 'Complete the final lesson' }}</strong>
                        </button>
                    @elseif($nextLesson)
                        <a href="{{ route('lms.front.courses.player', ['course' => $course, 'lesson' => $nextLesson]) }}" class="ainpa-player-next">
                            <span>{{ __('lms::ui.next-lesson') }}</span>
                            <strong dir="auto">{{ $nextLesson->title }}</strong>
                        </a>
                    @elseif($upcomingLesson)
                        <span class="ainpa-player-next is-disabled" aria-label="{{ __('lms::ui.next-lesson-locked-2') }}">
                            <span>{{ __('lms::ui.next-lesson-locked') }}</span>
                            <strong>{{ __('lms::ui.complete-this-lesson-to-continue') }}</strong>
                        </span>
                    @else
                        <a href="{{ route('lms.front.student.courses') }}" class="ainpa-player-next">
                            <span>{{ __('lms::ui.course-completed') }}</span>
                            <strong>{{ __('lms::ui.back-to-my-courses') }}</strong>
                        </a>
                    @endif
                </nav>
            </div>
        </section>
    </main>
<script src="{{ asset('platform/plugins/lms/frontend/js/lms-course-player.js') }}?v=1.5.11" defer></script>
</body>
</html>
