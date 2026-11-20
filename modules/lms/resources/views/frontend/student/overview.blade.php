<x-frontend-layout><main class="ainpa-portal" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">@include('lms::frontend.portal-nav',['portal'=>'student','portalAccountName'=>$student->name])<div class="ainpa-portal-wrap">
@php
    $courseNoun = static function (int $count): string {
        if ($count === 1) {
            return __('lms::ui.student_course_noun_singular');
        }
        if ($count === 2) {
            return __('lms::ui.student_course_noun_dual');
        }
        if ($count >= 3 && $count <= 10) {
            return __('lms::ui.student_course_noun_plural');
        }
        return __('lms::ui.student_course_noun_singular');
    };
@endphp
@if(session('status'))<div class="ainpa-portal-alert">{{ session('status') }}</div>@endif
<header class="ainpa-page-heading"><div><span>{{ __('lms::ui.student_overview_eyebrow') }}</span><h1>{{ __('lms::ui.student_overview_welcome') }}</h1><p dir="auto">{{ $student->name }}</p><p>{{ __('lms::ui.student_overview_intro') }}</p></div></header>
<section class="ainpa-compact-stats ainpa-overview-stats"><a href="{{ route('lms.front.student.courses') }}"><strong>{{ $totalCourses }}</strong>{{ $courseNoun($totalCourses) }} {{ __('lms::ui.student_stat_enrolled') }}</a><a href="{{ route('lms.front.student.courses', ['status' => 'in-progress']) }}"><strong>{{ $inProgressCourses }}</strong>{{ $courseNoun($inProgressCourses) }} {{ __('lms::ui.student_stat_in_progress') }}</a><a href="{{ route('lms.front.student.courses', ['status' => 'completed']) }}"><strong>{{ $completedCourses }}</strong>{{ $courseNoun($completedCourses) }} {{ __('lms::ui.student_stat_completed') }}</a></section>
@if($continueCourse)
@php($c=$continueCourse->course) @php($image=$c->featured_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->featured_image_path) : null)
<section class="ainpa-continue-card"><div class="ainpa-course-image">@if($image)<img src="{{ $image }}" alt="">@else<span>{{ strtoupper(\Illuminate\Support\Str::substr($c->title,0,2)) }}</span>@endif</div><div class="ainpa-continue-copy"><small>{{ __('lms::ui.student_portal_continue_learning') }}</small><h2>{{ $c->title }}</h2><p>{{ $c->instructor?->name ?: ($c->instructor_name ?: $lmsBrand) }}</p><p>{{ __('lms::ui.student_overview_next_lesson') }} {{ $continueCourse->next_lesson?->title ?: __('lms::ui.student_overview_no_lessons') }}</p><p>{{ __('lms::ui.student_portal_lessons_completed', ['completed' => $continueCourse->completed_lessons, 'total' => $continueCourse->total_lessons]) }}</p><div class="ainpa-progress"><span><i style="width:{{ $continueCourse->progress }}%"></i></span><b>{{ $continueCourse->progress }}%</b></div><a class="ainpa-btn ainpa-btn-primary" href="{{ route('lms.front.courses.player',['course'=>$c,'lesson'=>$continueCourse->last_lesson]) }}">{{ $continueCourse->action_label }}</a></div></section>
@else
<section class="ainpa-empty"><span aria-hidden="true">&#9432;</span><h2>{{ __('lms::ui.student_overview_no_courses') }}</h2><p>{{ __('lms::ui.student_overview_no_courses_hint') }}</p></section>
@endif
@if($courses->isNotEmpty())<section class="ainpa-section"><div class="ainpa-section-head"><div><h2>{{ __('lms::ui.student_overview_recent') }}</h2><p>{{ __('lms::ui.student_overview_recent_hint') }}</p></div><a href="{{ route('lms.front.student.courses') }}">{{ __('lms::ui.student_overview_view_all') }}</a></div><div class="ainpa-course-grid">@foreach($courses as $summary) @include('lms::frontend.student.course-card',['summary'=>$summary]) @endforeach</div></section>@endif
</div></main></x-frontend-layout>
