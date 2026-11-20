<x-frontend-layout><main class="ainpa-portal" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">@include('lms::frontend.portal-nav',['portal'=>'instructor','portalAccountName'=>$instructor->name])<div class="ainpa-portal-wrap">
    @if(session('status'))<div class="ainpa-portal-alert">{{ session('status') }}</div>@endif
    <header class="ainpa-page-heading"><div><span>{{ __('lms::ui.instructor_courses_eyebrow') }}</span><h1>{{ __('lms::ui.instructor_courses_trash_title') }}</h1><p>{{ __('lms::ui.instructor_courses_trash_intro') }}</p></div><a class="ainpa-btn ainpa-btn-secondary" href="{{ route('lms.front.instructor.courses') }}">{{ __('lms::ui.instructor_back_courses') }}</a></header>
    @if($courses->isNotEmpty())
        <div class="ainpa-instructor-course-list">
            @foreach($courses as $course)
                <article>
                    <div class="ainpa-instructor-course-image"><span>{{ strtoupper(\Illuminate\Support\Str::substr($course->title, 0, 2)) }}</span></div>
                    <div>
                        <small>{{ __('lms::ui.instructor_deleted_at', ['time' => $course->deleted_at?->diffForHumans()]) }}</small>
                        <h2>{{ $course->title }}</h2>
                        <p><span>{{ __('lms::ui.instructor_lessons_count', ['count' => $course->lessons_count]) }}</span><span>{{ __('lms::ui.instructor_students_count', ['count' => $course->enrollments_count]) }}</span></p>
                    </div>
                    <div class="ainpa-course-updated"><small>{{ __('lms::ui.instructor_actions') }}</small>
                        <div class="ainpa-inline-actions">
                            <form method="POST" action="{{ route('lms.front.instructor.courses.restore', $course->id) }}" style="margin:0">@csrf<button class="ainpa-btn ainpa-btn-primary" type="submit">{{ __('lms::ui.instructor_restore') }}</button></form>
                            <form method="POST" action="{{ route('lms.front.instructor.courses.force-destroy', $course->id) }}" style="margin:0" onsubmit="return confirm('{{ __('lms::ui.instructor_delete_course_forever_confirm') }}');">@csrf @method('DELETE')<button class="ainpa-btn ainpa-btn-secondary" type="submit">{{ __('lms::ui.instructor_delete_permanently') }}</button></form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <x-platform-pagination :paginator="$courses" />
    @else
        <section class="ainpa-empty"><span>&#9633;</span><h2>{{ __('lms::ui.instructor_trash_empty') }}</h2><p>{{ __('lms::ui.instructor_no_deleted_courses') }}</p></section>
    @endif
</div></main></x-frontend-layout>
