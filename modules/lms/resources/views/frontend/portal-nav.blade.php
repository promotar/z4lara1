@php
    $isStudent = $portal === 'student';
    $accountName = trim((string) $portalAccountName);
    $items = $isStudent ? [[__('lms::ui.student_portal_overview'),'lms.front.student.overview'],[__('lms::ui.student_portal_all_courses'),'lms.front.student.courses'],[__('lms::ui.student_portal_settings'),'lms.front.student.settings'],[__('lms::ui.student_portal_my_account'),'front.account']] : [[__('lms::ui.instructor_nav_courses'),'lms.front.instructor.courses'],[__('lms::ui.instructor_nav_lessons'),'lms.front.instructor.lessons'],[__('lms::ui.instructor_nav_students'),'lms.front.instructor.students'],[__('lms::ui.instructor_nav_settings'),'lms.front.instructor.settings'],[__('lms::ui.instructor_nav_my_account'),'front.account']];
@endphp
<nav class="ainpa-portal-nav" aria-label="{{ $isStudent ? __('lms::ui.student_portal_nav_student') : __('lms::ui.instructor_nav_aria') }}">
<a class="ainpa-portal-brand" href="{{ url('/') }}"><span>{{ $lmsBrandInitial }}</span><strong>{{ $lmsBrand }}</strong></a>
<div class="ainpa-portal-links">@foreach($items as [$label,$route])<a href="{{ route($route) }}" @class(['is-active'=>request()->routeIs($route) || request()->routeIs($route.'.*')])>{{ $label }}</a>@endforeach</div>
<div class="ainpa-portal-account"><span>{{ strtoupper(\Illuminate\Support\Str::substr($accountName,0,1)) }}</span><b dir="auto">{{ $accountName }}</b><form method="POST" action="{{ route('logout') }}">@csrf<button>{{ __('lms::ui.sign-out') }}</button></form></div></nav>
