<x-frontend-layout><main class="ainpa-portal" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">@include('lms::frontend.portal-nav',['portal'=>'instructor','portalAccountName'=>$instructor->name])<div class="ainpa-portal-wrap">
    @if(session('status'))<div class="ainpa-portal-alert">{{ session('status') }}</div>@endif
    <header class="ainpa-page-heading"><div><span>{{ __('lms::ui.instructor_lessons_eyebrow') }}</span><h1>{{ __('lms::ui.instructor_lessons_trash_title') }}</h1><p>{{ __('lms::ui.instructor_lessons_trash_intro') }}</p></div><a class="ainpa-btn ainpa-btn-secondary" href="{{ route('lms.front.instructor.lessons') }}">{{ __('lms::ui.instructor_back_lessons') }}</a></header>
    <section class="ainpa-portal-table"><div class="ainpa-table-scroll"><table><thead><tr><th>{{ __('lms::ui.instructor_lesson') }}</th><th>{{ __('lms::ui.instructor_course') }}</th><th>{{ __('lms::ui.instructor_type') }}</th><th>{{ __('lms::ui.instructor_deleted') }}</th><th></th></tr></thead><tbody>
        @forelse($lessons as $lesson)
            <tr>
                <td data-label="{{ __('lms::ui.instructor_lesson') }}"><strong>{{ $lesson->title }}</strong><small>{{ $lesson->module_title }}</small></td>
                <td data-label="{{ __('lms::ui.instructor_course') }}">{{ $lesson->course?->title }}</td>
                <td data-label="{{ __('lms::ui.instructor_type') }}"><span class="ainpa-type-pill">{{ __('lms::ui.instructor_lesson_type_'.($lesson->lesson_type ?: 'text')) }}</span></td>
                <td data-label="{{ __('lms::ui.instructor_deleted') }}">{{ $lesson->deleted_at?->format('d M Y') }}</td>
                <td>
                    <form method="POST" action="{{ route('lms.front.instructor.lessons.restore', $lesson->id) }}" style="margin:0;display:inline">@csrf<button class="ainpa-btn ainpa-btn-primary" type="submit">{{ __('lms::ui.instructor_restore') }}</button></form>
                    <form method="POST" action="{{ route('lms.front.instructor.lessons.force-destroy', $lesson->id) }}" style="margin:0;display:inline" onsubmit="return confirm('{{ __('lms::ui.instructor_delete_lesson_forever_confirm') }}');">@csrf @method('DELETE')<button type="submit" style="border:0;background:none;color:#b91c1c;cursor:pointer;font-weight:800;text-decoration:underline">{{ __('lms::ui.instructor_delete_permanently') }}</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="ainpa-empty"><span>&#9633;</span><h2>{{ __('lms::ui.instructor_trash_empty') }}</h2><p>{{ __('lms::ui.instructor_no_deleted_lessons') }}</p></div></td></tr>
        @endforelse
    </tbody></table></div><x-platform-pagination :paginator="$lessons" /></section>
</div></main></x-frontend-layout>
