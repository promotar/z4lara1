@if (session('store_success') || $errors->any())
    <div class="z4-account-alerts" aria-live="polite">
        @if (session('store_success'))
            <div class="z4-account-alert is-success">{{ session('store_success') }}</div>
        @endif
        @if ($errors->any())
            <div class="z4-account-alert is-error">
                <strong>{{ __('store::ui.please-review-the-following') }}</strong>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
