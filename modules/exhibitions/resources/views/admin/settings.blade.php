@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-settings-pages-1" data-exhibitions-stylesheet>@endpush
<x-app-layout>
<main class="z4-exhibitions-admin z4-exhibitions-settings" data-exhibitions-settings>
<div class="z4-exhibitions-container">
    @php
        $commissionRate = (float) ($catalogue['definitions']['exhibitions.commission.default_rate']['current'] ?? 20);
        $t = static function (?string $text): string {
            if ($text === null || trim($text) === '') {
                return '';
            }
            if (! preg_match('/[A-Za-z]{2}/', $text)) {
                return $text;
            }
            return __('exhibitions::ui.auto_'.substr(md5($text), 0, 10));
        };
    @endphp
    <header class="z4-settings-heading">
        <div>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_d6b5ffb78a') }}</span>
            <h1>{{ $t($activeSection['label']) }}</h1>
            <p>{{ $t($activeSection['description']) }}</p>
        </div>
        <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.overview') }}">{{ __('exhibitions::ui.auto_741540f0f2') }}</a>
    </header>

    @if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="z4-exhibitions-alert is-error" role="alert"><strong>{{ __('exhibitions::ui.auto_7920994c27') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="z4-settings-unsaved" data-unsaved-banner hidden>
        <span><strong>{{ __('exhibitions::ui.auto_604a9a8f13') }}</strong><small>{{ __('exhibitions::ui.auto_3e763f16e4') }}</small></span>
        <button type="button" data-discard>{{ __('exhibitions::ui.auto_98313f623b') }}</button>
    </div>

    <form method="post" action="{{ route('admin.plugins.exhibitions.settings.update') }}" class="z4-settings-form" data-settings-form>
        @csrf
        <div class="z4-settings-toolbar" data-save-toolbar @if(!$errors->any()) hidden @endif>
            <label><span>{{ __('exhibitions::ui.auto_ccf1214f9f') }} <b>*</b></span><input name="reason" value="{{ old('reason') }}" required minlength="8" maxlength="1000" placeholder="{{ __('exhibitions::ui.auto_b1840f4604') }}"></label>
            <div class="z4-settings-page-context"><strong>{{ $t($activeSection['label']) }}</strong><span>{{ __('exhibitions::ui.auto_c19a1c1226') }}</span></div>
            <div class="z4-settings-actions"><button type="submit" class="z4-exhibitions-button">{{ __('exhibitions::ui.auto_daa6b483e9') }}</button><button type="button" class="z4-exhibitions-button is-secondary" data-discard>{{ __('exhibitions::ui.auto_d94b42030b') }}</button></div>
        </div>

        <section class="z4-settings-section is-active" data-settings-section="{{ $activeSectionKey }}">
            @if($activeSectionKey === 'commission-earnings')
                <div class="z4-settings-note is-important">
                    <strong>{{ __('exhibitions::ui.auto_8fcc606adf') }}</strong>
                    <span>{{ __('exhibitions::ui.auto_18e4f88308') }}</span>
                    <div class="z4-commission-preview" data-commission-preview><span><b data-platform-rate>{{ number_format($commissionRate, 3) }}</b>{{ __('exhibitions::ui.auto_8b68cfa58b') }}</span><span><b data-artist-rate>{{ number_format(100 - $commissionRate, 3) }}</b>{{ __('exhibitions::ui.auto_95c192836c') }}</span></div>
                </div>
                <div class="z4-commission-example" aria-label="{{ __('exhibitions::ui.auto_9332e93163') }}">
                    <header><strong>{{ __('exhibitions::ui.auto_d065aef672') }}</strong><span>{{ __('exhibitions::ui.auto_27892b1661') }}</span></header>
                    <dl><div><dt>{{ __('exhibitions::ui.auto_8dd2e14b27') }}</dt><dd><b data-example-commission>{{ number_format(900 * $commissionRate / 100, 3) }}</b> JOD</dd></div><div><dt>{{ __('exhibitions::ui.auto_82169c325d') }}</dt><dd><b data-example-artist>{{ number_format(900 * (100 - $commissionRate) / 100, 3) }}</b> JOD</dd></div></dl>
                </div>
            @elseif($activeSectionKey === 'settlement-policy')
                <div class="z4-settings-note">
                    <strong>{{ __('exhibitions::ui.auto_8fcc606adf') }}</strong>
                    <span>{{ __('exhibitions::ui.auto_273853297f') }}</span>
                </div>
            @elseif($activeSectionKey === 'artwork-rules')
                <div class="z4-settings-note">
                    <strong>{{ __('exhibitions::ui.auto_8fcc606adf') }}</strong>
                    <span>{{ __('exhibitions::ui.auto_dc5836e226') }}</span>
                </div>
            @else
                <div class="z4-settings-note">
                    <strong>{{ __('exhibitions::ui.auto_8fcc606adf') }}</strong>
                    <span>{{ __('exhibitions::ui.auto_23b67b1515') }}</span>
                </div>
            @endif

            <div class="z4-settings-page-groups">
                @foreach($activeSection['groups'] as $settingsGroup)
                    @php
                        $visibleFields = collect($settingsGroup['fields'])->reject(fn ($field) => (bool) ($field['hidden'] ?? false));
                        $lastSaved = $visibleFields->pluck('last_saved_at')->filter()->sortDesc()->first();
                    @endphp
                    <section class="z4-settings-page-group">
                        <header>
                            <div><h2>{{ $t($settingsGroup['label']) }}</h2><p>{{ $t($settingsGroup['description']) }}</p></div>
                            <small>{{ __('exhibitions::ui.auto_bda94c915d') }}</small>
                        </header>
                        <div class="z4-settings-fields">
                            @foreach($visibleFields as $key => $setting)
                                @php
                                    $inputId = 'setting-'.substr(hash('sha256', $key), 0, 14);
                                    $current = old('settings.'.$key, $setting['current']);
                                    $locked = (bool) ($setting['locked'] ?? false);
                                @endphp
                                <article class="z4-setting-field {{ $setting['type'] === 'boolean' ? 'is-switch' : '' }} {{ $locked ? 'is-locked' : '' }} {{ $key === 'exhibitions.commission.default_rate' ? 'is-primary-setting' : '' }}" data-setting-key="{{ $key }}">
                                    <div class="z4-setting-copy"><label for="{{ $inputId }}">{{ $t($setting['label']) }}</label>@if($setting['description'])<small>{{ $t($setting['description']) }}</small>@endif</div>
                                    <div class="z4-setting-control">
                                        @if($setting['type'] === 'boolean')
                                            @if(!$locked)<input type="hidden" name="settings[{{ $key }}]" value="0">@endif
                                            <label class="z4-settings-switch"><input id="{{ $inputId }}" type="checkbox" name="settings[{{ $key }}]" value="1" @checked((bool) $current) @disabled($locked)><span aria-hidden="true"></span><em>{{ (bool) $current ? __('exhibitions::ui.on') : __('exhibitions::ui.off') }}</em></label>
                                        @elseif(isset($setting['options']))
                                            <select id="{{ $inputId }}" name="settings[{{ $key }}]" @disabled($locked)>@foreach($setting['options'] as $option)<option value="{{ $option }}" @selected((string) $current === (string) $option)>{{ $t($setting['option_labels'][$option] ?? Illuminate\Support\Str::headline($option)) }}</option>@endforeach</select>
                                        @elseif($setting['type'] === 'json')
                                            <textarea id="{{ $inputId }}" name="settings[{{ $key }}]" rows="3" @disabled($locked)>{{ is_array($current) ? implode(', ', $current) : $current }}</textarea>
                                        @else
                                            <input id="{{ $inputId }}" name="settings[{{ $key }}]" value="{{ $current }}" type="{{ in_array($setting['type'], ['integer', 'decimal'], true) ? 'number' : 'text' }}" @if($setting['type'] === 'decimal') step="0.001" @endif @isset($setting['min']) min="{{ $setting['min'] }}" @endisset @isset($setting['max']) max="{{ $setting['max'] }}" @endisset @disabled($locked)>
                                        @endif
                                        @if(isset($setting['input_help']))<small>{{ $t($setting['input_help']) }}</small>@endif
                                        @error('settings.'.$key)<em class="z4-field-error">{{ $message }}</em>@enderror
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
    </form>
</div>
</main>
<script>
(() => {
    const root = document.querySelector('[data-exhibitions-settings]');
    if (!root) return;
    const form = root.querySelector('[data-settings-form]');
    const banner = root.querySelector('[data-unsaved-banner]');
    const saveToolbar = root.querySelector('[data-save-toolbar]');
    const commissionInput = root.querySelector('[name="settings[exhibitions.commission.default_rate]"]');
    const payoutTiming = root.querySelector('[name="settings[exhibitions.settlement.eligibility_trigger]"]');
    const returnDaysField = root.querySelector('[data-setting-key="exhibitions.settlement.return_window_days"]');
    const setDirty = (value) => { banner.hidden = !value; saveToolbar.hidden = !value; };
    const updateCommissionPreview = () => {
        if (!commissionInput) return;
        const rate = Math.min(100, Math.max(0, Number.parseFloat(commissionInput.value) || 0));
        root.querySelector('[data-platform-rate]').textContent = rate.toFixed(3);
        root.querySelector('[data-artist-rate]').textContent = (100 - rate).toFixed(3);
        root.querySelector('[data-example-commission]').textContent = (900 * rate / 100).toFixed(3);
        root.querySelector('[data-example-artist]').textContent = (900 * (100 - rate) / 100).toFixed(3);
    };
    const updatePayoutTiming = () => { if (payoutTiming && returnDaysField) returnDaysField.hidden = payoutTiming.value === 'delivered'; };
    form.addEventListener('input', () => { updateCommissionPreview(); updatePayoutTiming(); setDirty(true); });
    form.addEventListener('change', (event) => {
        if (event.target.matches('.z4-settings-switch input')) event.target.closest('.z4-settings-switch').querySelector('em').textContent = event.target.checked ? __('exhibitions::ui.on') : __('exhibitions::ui.off');
        updatePayoutTiming(); setDirty(true);
    });
    root.querySelectorAll('[data-discard]').forEach((button) => button.addEventListener('click', () => {
        form.reset();
        form.querySelectorAll('.z4-settings-switch input').forEach((input) => { input.closest('.z4-settings-switch').querySelector('em').textContent = input.checked ? __('exhibitions::ui.on') : __('exhibitions::ui.off'); });
        updateCommissionPreview(); updatePayoutTiming(); setDirty(false);
    }));
    updateCommissionPreview(); updatePayoutTiming();
})();
</script>
</x-app-layout>
