<x-app-layout>
    <div class="lms-course-shell">
        <div class="lms-course-container">
            <div class="lms-course-topbar">
                <a href="{{ route('admin.plugins.lms.lessons') }}" class="lms-back-link">{{ __('lms::ui.auto_dbfa4f5111') }}</a>
                <div class="lms-course-title">
                    <h1>{{ $lesson->title }}</h1>
                    <p>{{ __('lms::ui.auto_25abf1e22c') }}</p>
                </div>
                <div class="lms-actions">
                    <a href="{{ route('admin.plugins.lms.lessons.create') }}" class="lms-btn lms-btn-ghost">{{ __('lms::ui.auto_1aaaa6bec4') }}</a>
                </div>
            </div>

            @if (session('status'))
                <div class="lms-alert lms-alert-success">{{ session('status') }}</div>
            @endif

            <div class="lms-course-grid">
                <section class="lms-card lms-preview-hero">
                    @if ($lesson->featured_image_path)
                        <img class="lms-preview-cover" src="{{ Storage::disk('public')->url($lesson->featured_image_path) }}" alt="{{ $lesson->title }}">
                    @endif
                    <div>
                        <span class="lms-status-pill">{{ ucfirst($lesson->status) }}</span>
                        <h2 style="font-size: 28px; margin-top: 14px;">{{ $lesson->title }}</h2>
                        <p>{{ $lesson->description }}</p>
                    </div>
                    <div>{!! nl2br(e($lesson->content)) !!}</div>
                </section>

                <aside class="lms-side-column">
                    <section class="lms-card">
                        <h3>{{ __('lms::ui.auto_b844170463') }}</h3>
                        <p><strong>{{ __('lms::ui.auto_a3ba972835') }}</strong> {{ $lesson->course?->title ?: '—' }}</p>
                        <p><strong>{{ __('lms::ui.auto_53b3cae427') }}</strong> {{ $lesson->module_title }}</p>
                        <p><strong>{{ __('lms::ui.auto_2ca3deb5cd') }}</strong> {{ $lesson->lesson_order }}</p>
                        <p><strong>{{ __('lms::ui.auto_e659b52eba') }}</strong> {{ ucfirst($lesson->lesson_type) }}</p>
                        <p><strong>{{ __('lms::ui.auto_6831155807') }}</strong> {{ $lesson->duration_hours }}h {{ $lesson->duration_minutes }}m</p>
                    </section>

                    <section class="lms-card">
                        <h3>{{ __('lms::ui.auto_bf733d8a93') }}</h3>
                        <p><strong>{{ __('lms::ui.auto_8ad79b2107') }}</strong> {{ ucfirst($lesson->visibility) }}</p>
                        <p><strong>{{ __('lms::ui.auto_8c751c4aab') }}</strong> {{ ucfirst(str_replace('_', ' ', $lesson->availability)) }}</p>
                        <p><strong>{{ __('lms::ui.auto_6cec6deff2') }}</strong> {{ $lesson->free_preview ? 'Enabled' : 'Disabled' }}</p>
                    </section>

                    <section class="lms-card">
                        <h3>{{ __('lms::ui.auto_7e2708aeb6') }}</h3>
                        <p>{{ __('lms::ui.auto_17c123d76c') }}</p>
                        <p>{{ __('lms::ui.auto_a71ce9ffcc') }}</p>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
