<section class="account-extension-section">
    <div class="account-section-heading">
        <div>
            <h2>{{ __('lms::ui.account_course_codes') }}</h2>
            <p>{{ __('lms::ui.account_course_codes_desc') }}</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <a class="account-section-action" href="{{ $exportUrl }}">{{ __('lms::ui.export_codes') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ $generateUrl }}" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin:0 0 1.1rem">
        @csrf
        <label style="flex:1 1 240px;min-width:200px">
            <span>{{ __('lms::ui.auto_3730c98664') }}</span>
            <select class="lms-select" name="course_id" required>
                <option value="">{{ __('lms::ui.auto_96427a1d2d') }}</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                @endforeach
            </select>
        </label>
        <label style="width:130px">
            <span>{{ __('lms::ui.auto_a4560ca70f') }}</span>
            <input class="lms-input" type="number" name="quantity" min="1" max="100" value="10" required>
        </label>
        <button class="account-section-action" type="submit">{{ __('lms::ui.auto_6ff087069e') }}</button>
    </form>

    <div class="account-item-list">
        @forelse ($codes as $code)
            <div class="account-item">
                <span>
                    <strong dir="ltr">{{ $code->code }}</strong>
                    <small>
                        {{ $code->course_title ?: __('lms::ui.account_unavailable_course') }}
                        @if ($code->created_at)
                            · {{ \Illuminate\Support\Carbon::parse($code->created_at)->format('d M Y H:i') }}
                        @endif
                    </small>
                </span>
                <span class="lms-status-badge lms-code-status-{{ $code->status }}">
                    {{ $code->status === 'used' ? __('lms::ui.auto_ec53a8c4f0') : __('lms::ui.code_status_available') }}
                </span>
            </div>
        @empty
            <p style="color:#6b7280">{{ __('lms::ui.no_codes_yet') }}</p>
        @endforelse
    </div>
</section>
