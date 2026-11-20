<x-app-layout>
    <div class="lms-course-shell">
        <div class="lms-course-container">
            <div class="lms-course-topbar">
                <a href="{{ route('admin.plugins.lms.courses') }}" class="lms-back-link">{{ __('lms::ui.auto_aebc0de88e') }}</a>
                <div class="lms-course-title">
                    <h1>{{ $course->title }}</h1>
                    <p>{{ __('lms::ui.auto_372739c972') }}</p>
                </div>
                <div class="lms-actions">
                    <a href="{{ route('admin.plugins.lms.courses.create') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_1aaaa6bec4') }}</a>
                </div>
            </div>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            <div class="lms-course-grid">
                <section class="lms-card lms-preview-hero">
                    @if ($course->featured_image_path)
                        <img class="lms-preview-cover" src="{{ Storage::disk('public')->url($course->featured_image_path) }}" alt="{{ $course->title }}">
                    @endif
                    <div>
                        <span class="lms-status-pill">{{ ucfirst($course->status) }}</span>
                        <h2 style="font-size: 28px; margin-top: 14px;">{{ $course->title }}</h2>
                        @if ($course->subtitle)
                            <p>{{ $course->subtitle }}</p>
                        @endif
                        <p>{{ $course->short_description }}</p>
                    </div>
                    <div>{!! nl2br(e($course->description)) !!}</div>
                </section>

                <aside class="lms-side-column">
                    <section class="lms-card">
                        <h3>{{ __('lms::ui.auto_d5cef68955') }}</h3>
                        <p><strong>{{ __('lms::ui.auto_56a8a9eb05') }}</strong> {{ $course->category }}</p>
                        <p><strong>{{ __('lms::ui.auto_07ad815187') }}</strong> {{ $course->level }}</p>
                        <p><strong>{{ __('lms::ui.auto_0885f0c211') }}</strong> {{ $course->language }}</p>
                        <p><strong>{{ __('lms::ui.auto_0fe0865344') }}</strong> {{ $course->instructor_name }}</p>
                        <p><strong>{{ __('lms::ui.auto_8ad79b2107') }}</strong> {{ ucfirst($course->visibility) }}</p>
                    </section>

                    <section class="lms-card">
                        <h3>{{ __('lms::ui.auto_e22ac25b06') }}</h3>
                        @if ($course->pricing_type === 'paid')
                            <p><strong>{{ $course->currency }} {{ $course->sale_price ?: $course->regular_price }}</strong></p>
                            @if ($course->sale_price)
                                <p class="lms-help">{{ __('lms::ui.auto_7dcb1596cf') }}</p>
                            @endif
                        @else
                            <p><strong>{{ __('lms::ui.auto_b24ce0cd39') }}</strong></p>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
