@php
    $statusLabels = [
        'published' => __('lms::ui.admin_status_published'),
        'scheduled' => __('lms::ui.admin_status_scheduled'),
        'draft' => __('lms::ui.admin_status_draft'),
        'archived' => __('lms::ui.admin_status_archived'),
    ];

    $activityIcons = [
        'green' => 'book',
        'blue' => 'play',
        'purple' => 'users',
        'orange' => 'trend',
        'gray' => 'report',
    ];
@endphp

<link rel="stylesheet" href="{{ route('admin.plugins.lms.overview.styles') }}?v={{ filemtime(base_path('modules/lms/resources/assets/admin/overview.css')) }}">

<x-app-layout>
    <div class="lms-course-shell lms-overview-page" data-lms-overview-page>
        <div class="lms-course-container">
            <header class="lms-overview-heading">
                <div>
                    <h1>{{ __('lms::ui.auto_3b878279a0') }}</h1>
                    <p>{{ __('lms::ui.auto_a76552951e') }}</p>
                </div>

                <div class="lms-overview-actions">
                    <a href="{{ route('admin.plugins.lms.overview.export', ['range' => $range['key']]) }}" class="lms-btn lms-btn-ghost">
                        <span class="lms-overview-icon lms-overview-icon-export" aria-hidden="true"></span>
                        {{ __('lms::ui.auto_9d3fbcaef4') }}
                    </a>
                    <a href="{{ route('admin.plugins.lms.courses.create') }}" class="lms-btn lms-btn-primary lms-add-course-btn" style="color:#fff!important">
                        <span class="lms-overview-icon lms-overview-icon-plus" aria-hidden="true"></span>
                        {{ __('lms::ui.auto_a5a0f5c174') }}
                    </a>
                </div>
            </header>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="lms-alert lms-alert-error">{{ $errors->first() }}</div>
            @endif

            <section class="lms-stat-grid" aria-label="{{ __('lms::ui.auto_bf0ca224fd') }}">
                @foreach ($stats as $stat)
                    <article class="lms-stat-card">
                        <div class="lms-stat-icon lms-stat-{{ $stat['tone'] }}">
                            <span class="lms-overview-icon lms-overview-icon-{{ $stat['icon'] }}" aria-hidden="true"></span>
                        </div>
                        <div>
                            <span>{{ $stat['label'] }}</span>
                            <strong>{{ $stat['display'] }}</strong>
                            <small>{{ $stat['note'] }}</small>
                        </div>
                    </article>
                @endforeach
            </section>

            <div class="lms-overview-grid">
                <section class="lms-overview-card lms-overview-enrollment-card">
                    <div class="lms-card-title-row lms-overview-chart-heading">
                        <div>
                            <h2>{{ __('lms::ui.auto_0b8c409c0c') }}</h2>
                            <p>{{ $range['label'] }}</p>
                        </div>
                        <div class="lms-range-tabs" aria-label="{{ __('lms::ui.auto_976823932f') }}">
                            <a @class(['active' => $range['key'] === '7d']) @if($range['key'] === '7d') aria-current="page" @endif href="{{ route('admin.plugins.lms.overview', ['range' => '7d']) }}">{{ __('lms::ui.auto_374616a0b7') }}</a>
                            <a @class(['active' => $range['key'] === '30d']) @if($range['key'] === '30d') aria-current="page" @endif href="{{ route('admin.plugins.lms.overview', ['range' => '30d']) }}">{{ __('lms::ui.auto_9c680defe5') }}</a>
                            <a @class(['active' => $range['key'] === '12m']) @if($range['key'] === '12m') aria-current="page" @endif href="{{ route('admin.plugins.lms.overview', ['range' => '12m']) }}">{{ __('lms::ui.auto_f535419642') }}</a>
                        </div>
                    </div>

                    <div class="lms-line-chart" role="img" aria-label="{{ __('lms::ui.auto_9cfe9d5c62') }}">
                        <div class="lms-chart-axis">
                            <span>{{ number_format($chart['max']) }}</span>
                            <span>{{ number_format((int) round($chart['max'] * .66)) }}</span>
                            <span>{{ number_format((int) round($chart['max'] * .33)) }}</span>
                            <span>0</span>
                        </div>
                        <svg viewBox="0 0 560 190" preserveAspectRatio="none" aria-hidden="true">
                            <g class="lms-grid-lines">
                                <line x1="20" y1="20" x2="540" y2="20"></line>
                                <line x1="20" y1="66" x2="540" y2="66"></line>
                                <line x1="20" y1="112" x2="540" y2="112"></line>
                                <line x1="20" y1="158" x2="540" y2="158"></line>
                            </g>
                            <polyline class="lms-line-purple" points="{{ $chart['studentsPoints'] }}" transform="translate(20 12)"></polyline>
                            <polyline class="lms-line-green" points="{{ $chart['enrollmentPoints'] }}" transform="translate(20 12)"></polyline>
                        </svg>
                    </div>

                    <div class="lms-chart-labels">
                        @foreach ($chart['labels'] as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>

                    <div class="lms-chart-legend">
                        <span><i class="purple"></i> {{ __('lms::ui.auto_be274ceab8') }}</span>
                        <span><i class="green"></i> {{ __('lms::ui.auto_f4e3c82720') }}</span>
                    </div>
                </section>

                <section class="lms-overview-card lms-overview-status-card">
                    <h2>{{ __('lms::ui.auto_2701f6cc0e') }}</h2>
                    <div class="lms-course-status-layout">
                        <div class="lms-donut" style="{{ $courseStatus['donutStyle'] }}">
                            <div>
                                <strong>{{ number_format($courseStatus['total']) }}</strong>
                                <span>{{ __('lms::ui.auto_8caaae88bf') }}</span>
                            </div>
                        </div>
                        <div class="lms-status-list">
                            @foreach ($courseStatus['segments'] as $segment)
                                <div>
                                    <span class="lms-status-dot" style="background: {{ $segment['color'] }}"></span>
                                    <p>{{ $segment['label'] }}</p>
                                    <strong>{{ number_format($segment['count']) }} ({{ $segment['percent'] }}%)</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <a class="lms-btn lms-btn-ghost lms-overview-wide-btn" href="{{ route('admin.plugins.lms.courses') }}">{{ __('lms::ui.auto_1ed3777f6b') }}</a>
                </section>

                @if ($upcomingLiveClasses !== [])
                <section class="lms-overview-card lms-overview-live-card">
                    <div class="lms-card-title-row">
                        <h2>{{ __('lms::ui.auto_3d37becff2') }}</h2>
                        <a href="{{ route('admin.plugins.lms.lessons') }}">{{ __('lms::ui.auto_d8d7ad6abf') }}</a>
                    </div>

                    <div class="lms-live-list">
                        @foreach ($upcomingLiveClasses as $lesson)
                            <article>
                                <div class="lms-live-icon">
                                    <span class="lms-overview-icon lms-overview-icon-video" aria-hidden="true"></span>
                                </div>
                                <div>
                                    <h3>{{ $lesson['title'] }}</h3>
                                    <p>{{ $lesson['course'] }}</p>
                                    <small>{{ $lesson['date'] }} • {{ $lesson['time'] }}</small>
                                    <small>{{ __('lms::ui.auto_949cebd73d') }}</small>
                                </div>
                                <a class="lms-btn lms-btn-ghost" href="{{ route('admin.plugins.lms.lessons.preview', $lesson['id']) }}">{{ __('lms::ui.auto_72c390a092') }}</a>
                            </article>
                        @endforeach
                    </div>

                    <a class="lms-btn lms-btn-ghost lms-overview-wide-btn" href="{{ route('admin.plugins.lms.lessons') }}">{{ __('lms::ui.auto_b603795a36') }}</a>
                </section>
                @endif

                <section @class(['lms-overview-card', 'lms-top-courses-card', 'lms-top-courses-card--full' => $upcomingLiveClasses === []])>
                    <div class="lms-card-title-row">
                        <h2>{{ __('lms::ui.auto_26ebf7160b') }}</h2>
                        <a href="{{ route('admin.plugins.lms.courses') }}">{{ __('lms::ui.auto_d8d7ad6abf') }}</a>
                    </div>

                    <div class="lms-overview-table-wrap">
                        <table class="lms-overview-table">
                            <thead>
                                <tr>
                                    <th>{{ __('lms::ui.auto_3730c98664') }}</th>
                                    <th>{{ __('lms::ui.auto_07f8bcc8ac') }}</th>
                                    <th>{{ __('lms::ui.auto_aba064f896') }}</th>
                                    <th>{{ __('lms::ui.auto_2fea59b7e4') }}</th>
                                    <th>{{ __('lms::ui.auto_ec53a8c4f0') }}</th>
                                    <th>{{ __('lms::ui.auto_06df33001c') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topCourses as $course)
                                    <tr>
                                        <td>
                                            <div class="lms-overview-course-cell">
                                                @if ($course['image'])
                                                    <img src="{{ $course['image'] }}" alt="">
                                                @else
                                                    <span>{{ strtoupper(substr($course['title'], 0, 1)) }}</span>
                                                @endif
                                                <div>
                                                    <strong dir="auto">{{ $course['title'] }}</strong>
                                                    <small>{{ $course['code'] }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $course['instructor'] }}</td>
                                        <td>{{ number_format($course['students']) }}</td>
                                        <td>
                                            <div class="lms-progress">
                                                <span style="width: {{ $course['completion'] }}%"></span>
                                            </div>
                                            <small>{{ $course['completion'] }}%</small>
                                        </td>
                                        <td><span class="lms-status-badge lms-status-{{ $course['status'] }}">{{ $statusLabels[$course['status']] ?? ucfirst($course['status']) }}</span></td>
                                        <td>
                                            <details class="lms-row-menu">
                                                <summary class="lms-row-action" aria-label="{{ __('lms::ui.auto_f1cef078d8') }}">⋮</summary>
                                                <div class="lms-row-menu-panel" aria-label="{{ __('lms::ui.auto_1fd154f3b3') }}">
                                                    <a href="{{ route('admin.plugins.lms.courses.preview', $course['id']) }}">{{ __('lms::ui.auto_7c7e82b198') }}</a>
                                                    <a href="{{ route('admin.plugins.lms.courses.edit', $course['id']) }}">{{ __('lms::ui.auto_83846c9072') }}</a>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="lms-empty-state">{{ __('lms::ui.auto_98068e6dde') }}</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="lms-overview-card lms-recent-enrollments-card">
                    <div class="lms-card-title-row">
                        <h2>{{ __('lms::ui.auto_6f5da022ec') }}</h2>
                        <a href="{{ route('admin.plugins.lms.students') }}">{{ __('lms::ui.auto_d8d7ad6abf') }}</a>
                    </div>

                    <div class="lms-enrollment-list">
                        @forelse ($recentEnrollments as $student)
                            <article>
                                @if ($student['avatar'])
                                    <img src="{{ $student['avatar'] }}" alt="">
                                @else
                                    <span>{{ strtoupper(substr($student['name'], 0, 1)) }}</span>
                                @endif
                                <div>
                                    <strong dir="auto">{{ $student['name'] }}</strong>
                                    <small dir="auto">{{ $student['course'] }}</small>
                                </div>
                                <p>{{ $student['date'] }}</p>
                                <a class="lms-status-badge lms-student-status-{{ $student['status'] }}" href="{{ route('admin.plugins.lms.students.show', $student['id']) }}">{{ ucfirst($student['status']) }}</a>
                            </article>
                        @empty
                            <div class="lms-empty-state">{{ __('lms::ui.auto_813d8d473e') }}</div>
                        @endforelse
                    </div>
                </section>

                <section class="lms-overview-card lms-activity-card">
                    <div class="lms-card-title-row">
                        <h2>{{ __('lms::ui.auto_7377550f85') }}</h2>
                        <a href="{{ route('admin.plugins.lms.courses') }}">{{ __('lms::ui.auto_d8d7ad6abf') }}</a>
                    </div>

                    <div class="lms-activity-strip">
                        @forelse ($recentActivity as $activity)
                            <article>
                                <span class="lms-activity-icon lms-activity-{{ $activity['tone'] }}">
                                    <span class="lms-overview-icon lms-overview-icon-{{ $activityIcons[$activity['tone']] ?? 'report' }}" aria-hidden="true"></span>
                                </span>
                                <strong>{{ $activity['title'] }}</strong>
                                <small>{{ $activity['meta'] }}</small>
                            </article>
                        @empty
                            <div class="lms-empty-state">{{ __('lms::ui.auto_0c672491fb') }}</div>
                        @endforelse
                    </div>
                </section>

                <section class="lms-overview-card lms-quick-actions-card">
                    <h2>{{ __('lms::ui.auto_57aee38348') }}</h2>
                    <div class="lms-quick-actions-grid">
                        <a href="{{ route('admin.plugins.lms.lessons.create') }}"><span><i class="lms-overview-icon lms-overview-icon-play" aria-hidden="true"></i></span> {{ __('lms::ui.auto_f9062fcfa6') }} <b>›</b></a>
                        <a href="{{ route('admin.plugins.lms.students.create') }}"><span><i class="lms-overview-icon lms-overview-icon-users" aria-hidden="true"></i></span> {{ __('lms::ui.auto_bcb6a599b9') }} <b>›</b></a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
