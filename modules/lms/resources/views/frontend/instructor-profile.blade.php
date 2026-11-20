@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp

<x-frontend-layout>
    <x-slot name="head">
        <title>{{ $instructorName }} - Instructor Profile</title>
        <meta name="description" content="Explore courses by {{ $instructorName }}.">
        <link rel="stylesheet" href="{{ asset('platform/plugins/lms/frontend/css/lms-instructor-profile.css') }}?v=20261005-square">
    </x-slot>

    <main class="lms-instructor-profile" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <div class="lms-instructor-profile__shell">
            <a class="lms-instructor-profile__back" href="{{ route('lms.front.courses') }}">{{ __('lms::ui.back-to-courses') }}</a>

            <section class="lms-instructor-profile__hero">
                <span class="lms-instructor-profile__avatar">{{ Str::upper(Str::substr($instructorName, 0, 1)) }}</span>
                <div>
                    <p>{{ __('lms::ui.instructor-profile') }}</p>
                    <h1>{{ $instructorName }}</h1>
                    <span>{{ $instructorTitle }}</span>
                </div>
                <dl>
                    <div><dt>{{ __('lms::ui.courses') }}</dt><dd>{{ $courses->count() }}</dd></div>
                    <div><dt>{{ __('lms::ui.lessons') }}</dt><dd>{{ $lessonCount }}</dd></div>
                    <div><dt>{{ __('lms::ui.students') }}</dt><dd>{{ $studentCount }}</dd></div>
                </dl>
            </section>

            <section>
                <h2>Courses by {{ $instructorName }}</h2>
                <div class="lms-instructor-profile__courses">
                    @foreach($courses as $course)
                        @php($imageUrl = $course->featured_image_path ? Storage::disk('public')->url($course->featured_image_path) : null)
                        <a class="lms-instructor-profile__course" href="{{ route('lms.front.courses.show', $course) }}">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $course->title }}">
                            @else
                                <span class="lms-instructor-profile__placeholder">{{ Str::upper(Str::substr($course->title, 0, 1)) }}</span>
                            @endif
                            <div>
                                <span>{{ $course->category ?: 'Course' }}</span>
                                <h3>{{ $course->title }}</h3>
                                <p>{{ $course->subtitle ?: $course->short_description ?: 'Explore this course.' }}</p>
                                <small>{{ $course->lessons_count }} lessons · {{ $course->enrollments_count }} students</small>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </main>
</x-frontend-layout>
