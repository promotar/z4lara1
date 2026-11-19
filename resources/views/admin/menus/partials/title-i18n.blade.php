{{--
    Per-language name field (shared by menu items and menu sections).
    Values are submitted as:
      translations[locale] => name  +  <baseField> (default language, kept for lists)
--}}
@php
    $i18nLanguages = $platformLanguages ?? ['en' => 'English'];
    $i18nDefault = app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();
    $i18nBaseField = $baseField ?? 'title';
    // Stored translations live in `<baseField>_translations` (title_translations
    // for menu items, name_translations for menu sections). An explicit
    // `$translations` array wins when provided.
    $i18nStored = $translations ?? ($item?->{$i18nBaseField.'_translations'} ?? []);

    if (is_string($i18nStored)) {
        $i18nStored = json_decode($i18nStored, true) ?: [];
    }

    $i18nValues = old('translations', $values ?? (is_array($i18nStored) ? $i18nStored : []));

    if (! is_array($i18nValues)) {
        $i18nValues = [];
    }

    $i18nBase = (string) old($i18nBaseField, $baseValue ?? ($item->{$i18nBaseField} ?? ''));

    if (trim((string) ($i18nValues[$i18nDefault] ?? '')) === '' && $i18nBase !== '') {
        $i18nValues[$i18nDefault] = $i18nBase;
    }

    $i18nSelected = (string) old('translation_locale', $i18nDefault);

    if (! isset($i18nLanguages[$i18nSelected])) {
        $i18nSelected = $i18nDefault;
    }

    $i18nPlaceholder = $placeholder ?? __('core.menus.display_name');
@endphp

<div class="z4-i18n-field" data-i18n-field data-default="{{ $i18nDefault }}">
    <div class="z4-i18n-row">
        <select class="admin-input z4-i18n-lang" data-i18n-lang aria-label="{{ __('core.menus.language') }}">
            @foreach ($i18nLanguages as $i18nCode => $i18nLabel)
                <option value="{{ $i18nCode }}" @selected($i18nSelected === $i18nCode)>
                    {{ $i18nLabel }}{{ $i18nCode === $i18nDefault ? ' • '.__('core.menus.language_base') : '' }}
                </option>
            @endforeach
        </select>

        <input
            type="text"
            class="admin-input z4-i18n-text"
            data-i18n-text
            value="{{ $i18nValues[$i18nSelected] ?? '' }}"
            placeholder="{{ $i18nPlaceholder }}"
            autocomplete="off"
        >
    </div>

    <div class="z4-i18n-hidden" data-i18n-hidden>
        @foreach ($i18nLanguages as $i18nCode => $i18nLabel)
            <input type="hidden" name="translations[{{ $i18nCode }}]" value="{{ $i18nValues[$i18nCode] ?? '' }}" data-locale="{{ $i18nCode }}">
        @endforeach
    </div>

    <input type="hidden" name="translation_locale" value="{{ $i18nSelected }}" data-i18n-selected>
    <input type="hidden" name="{{ $i18nBaseField }}" value="{{ $i18nValues[$i18nDefault] ?? '' }}" data-i18n-base>

    <p class="z4-i18n-hint">{{ __('core.menus.languages_hint') }}</p>
</div>

<style>
    .z4-i18n-field { display: flex; flex-direction: column; gap: 6px; width: 100%; }
    .z4-i18n-row { display: flex; align-items: stretch; gap: 8px; }

    .z4-i18n-field .z4-i18n-lang {
        flex: 0 0 132px;
        min-width: 132px;
        padding: 8px 10px;
        border: 1px solid #d7dde6;
        border-radius: 9px;
        background: #f8fafc;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .z4-i18n-field .z4-i18n-text {
        flex: 1 1 auto;
        min-width: 0;
        padding: 9px 12px;
        border: 1px solid #d7dde6;
        border-radius: 9px;
        background: #ffffff;
        color: #0f172a;
        font-size: 13px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .z4-i18n-field .z4-i18n-text:focus,
    .z4-i18n-field .z4-i18n-lang:focus {
        outline: none;
        border-color: #d71913;
        box-shadow: 0 0 0 3px rgba(215, 25, 19, .12);
    }

    .z4-i18n-field .z4-i18n-hint {
        margin: 0;
        font-size: 10.5px;
        line-height: 1.6;
        color: #94a3b8;
    }

    .z4-i18n-hidden { display: none; }
</style>

<script>
(function () {
    var init = function (field) {
        if (!field || field.dataset.i18nReady === '1') return;
        field.dataset.i18nReady = '1';

        var select = field.querySelector('[data-i18n-lang]');
        var text = field.querySelector('[data-i18n-text]');
        var selected = field.querySelector('[data-i18n-selected]');
        var base = field.querySelector('[data-i18n-base]');
        var defaultLocale = field.dataset.default;
        var previous = select.value;

        var hiddenFor = function (code) {
            return field.querySelector('[data-i18n-hidden] [data-locale="' + code + '"]');
        };

        var syncDefault = function () {
            var baseInput = hiddenFor(defaultLocale);
            if (base && baseInput) base.value = baseInput.value;
        };

        text.addEventListener('input', function () {
            var target = hiddenFor(select.value);
            if (target) target.value = text.value;
            syncDefault();
        });

        select.addEventListener('change', function () {
            var oldTarget = hiddenFor(previous);
            if (oldTarget) oldTarget.value = text.value;

            previous = select.value;
            var next = hiddenFor(previous);
            text.value = next ? next.value : '';
            if (selected) selected.value = previous;
            syncDefault();
        });

        var initial = hiddenFor(select.value);
        if (initial) initial.value = text.value;
        if (selected) selected.value = select.value;
        syncDefault();
    };

    document.querySelectorAll('[data-i18n-field]').forEach(init);
})();
</script>
