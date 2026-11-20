<section class="account-extension-section">
    <div class="account-section-heading">
        <div>
            <h2>{{ __('lms::ui.teaching') }}</h2>
            <p>{{ __('lms::ui.create-and-manage-your-courses-diplomas-and-lessons') }}</p>
        </div>
        <a class="account-section-action" href="{{ $dashboardUrl }}">{{ __('lms::ui.open-teaching-dashboard') }}</a>
    </div>

    <div class="account-item-list">
        <a class="account-item" href="{{ $dashboardUrl }}">
            <span><strong>{{ __('lms::ui.my-courses-and-diplomas') }}</strong><small>{{ __('lms::ui.assigned-and-created-learning-programs') }}</small></span>
            <span>{{ $courseCount }}</span>
        </a>
        <a class="account-item" href="{{ route('lms.front.instructor.lessons') }}">
            <span><strong>{{ __('lms::ui.lessons') }}</strong><small>{{ __('lms::ui.course-curriculum-and-lesson-content') }}</small></span>
            <span>{{ $lessonCount }}</span>
        </a>
    </div>

    @if($createCourseUrl || $createLessonUrl)
        <div class="account-section-heading lms-account-actions lms-account-actions-start">
            @if($createCourseUrl)<a class="account-section-action" href="{{ $createCourseUrl }}">{{ __('lms::ui.create-course') }}</a>@endif
            @if($createLessonUrl)<a class="account-section-action" href="{{ $createLessonUrl }}">{{ __('lms::ui.create-lesson') }}</a>@endif
        </div>
    @endif
</section>

@once
    <style>
        .account-subnav{display:grid;gap:.2rem;margin:.15rem 0 .45rem;padding-inline-start:.85rem;border-inline-start:1px solid rgb(255 255 255 / 20%)}
        .account-subnav a{display:block;padding:.34rem .5rem;border-radius:.5rem;color:#f0c9cc;font-size:.78rem;font-weight:700;text-decoration:none}
        .account-subnav a:hover{background:rgb(255 255 255 / 8%);color:#fff}
        .account-subnav a.is-sub{padding-inline-start:1.15rem;font-size:.74rem;opacity:.92}
        .account-subnav[hidden]{display:none !important}
        .account-subnav__title{padding:.28rem .5rem 0;color:#e8b3b7;font-size:.68rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .account-subnav__toggle{display:flex;width:100%;gap:.4rem;align-items:center;border:0;background:none;padding:.34rem .5rem 0;color:#e8b3b7;font:inherit;font-size:.68rem;font-weight:800;letter-spacing:.04em;text-align:start;text-transform:uppercase;cursor:pointer}
        .account-subnav__toggle::after{content:"\25BE";margin-inline-start:auto;transition:transform .15s ease}
        .account-subnav__toggle[aria-expanded="true"]::after{transform:rotate(180deg)}
        .account-subnav a[hidden]{display:none !important}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tab = document.getElementById('account-tab-teaching');
            if (!tab || document.getElementById('lms-teaching-subnav')) { return; }
            var items = [
                [@json(__('lms::ui.account_add_course')), '{{ route('lms.front.instructor.courses.create', [], false) }}', false],
                [@json(__('lms::ui.account_add_lesson')), '{{ route('lms.front.instructor.lessons.create', [], false) }}', false],
                [@json(__('lms::ui.account_trash')), null, false],
                [@json(__('lms::ui.account_deleted_courses')), '{{ route('lms.front.instructor.courses.trash', [], false) }}', true],
                [@json(__('lms::ui.account_deleted_lessons')), '{{ route('lms.front.instructor.lessons.trash', [], false) }}', true]
            ];
            var nav = document.createElement('nav');
            nav.id = 'lms-teaching-subnav';
            nav.className = 'account-subnav';
            nav.setAttribute('aria-label', @json(__('lms::ui.account_teaching_sections')));
            items.forEach(function (item) {
                if (!item[1]) {
                    var toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'account-subnav__toggle';
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.textContent = item[0];
                    toggle.addEventListener('click', function () {
                        var open = toggle.getAttribute('aria-expanded') === 'true';
                        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
                        nav.querySelectorAll('.is-trash-item').forEach(function (child) { child.hidden = open; });
                    });
                    nav.appendChild(toggle);
                    return;
                }
                var a = document.createElement('a');
                a.href = item[1];
                a.textContent = item[0];
                if (item[2]) { a.className = 'is-sub is-trash-item'; a.hidden = true; }
                nav.appendChild(a);
            });
            tab.insertAdjacentElement('afterend', nav);
            var sync = function () { nav.hidden = ! tab.classList.contains('is-active'); };
            new MutationObserver(sync).observe(tab, { attributes: true, attributeFilter: ['class'] });
            sync();
        });
    </script>
@endonce
