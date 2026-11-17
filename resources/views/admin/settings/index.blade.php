<x-app-layout>
    @php
                $translate = fn ($text) => \App\Platform\Core\Localization\Translation::label(
            'core.settings.'.\Illuminate\Support\Str::slug((string) $text),
            (string) $text
        );
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $translate('Settings') }}</h2>
    </x-slot>


    <div class="wp-settings py-8">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

                        <div class="settings-tab-bar flex flex-wrap gap-1">
                @foreach ($groups as $groupKey => $group)
                    <button
                        type="button"
                        class="settings-tab {{ $loop->first ? 'is-active' : '' }}"
                        data-settings-tab="{{ $groupKey }}"
                    >
                        {{ $translate($group['label']) }}
                    </button>
                @endforeach
                <button type="button" class="settings-tab" data-settings-tab="language-translations">
                    {{ __('core.settings.translations') }}
                </button>
                <button type="button" class="settings-tab" data-settings-tab="core-updates">
                    {{ __('core.updates.title') }}
                </button>
            </div>

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                @foreach ($groups as $groupKey => $group)
                    <section
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                        data-settings-panel="{{ $groupKey }}"
                        @unless ($loop->first) hidden @endunless
                    >
                        <div class="settings-panel-header border-b border-gray-200 bg-white px-6 py-5">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $translate($group['label']) }}</h3>
                            @if ($group['description'] !== '')
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">{{ $translate($group['description']) }}</p>
                            @endif
                        </div>

                        <div class="settings-field-list space-y-1">
                            @foreach ($group['fields'] as $fieldKey => $field)
                                @continue($groupKey === 'general' && in_array($fieldKey, ['custom_date_format', 'custom_time_format'], true))
                                <div class="settings-field-row grid gap-4 px-6 py-5 md:grid-cols-12 md:items-start">
                                    <div class="md:col-span-3">
                                        <x-input-label :for="$groupKey.'_'.$fieldKey" :value="$translate($field['label'])" />
                                        @if (! empty($field['help_text']))
                                            <p class="mt-2 text-xs leading-5 text-gray-500">{{ $translate($field['help_text']) }}</p>
                                        @endif
                                    </div>

                                    <div class="max-w-xl md:col-span-9">
                                        @if ($field['type'] === 'textarea')
                                            <textarea
                                                id="{{ $groupKey }}_{{ $fieldKey }}"
                                                name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                rows="4"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                @disabled($groupKey === 'general' && $fieldKey === 'site_language' && ! ($isSuperAdmin ?? false))
                                            >{{ $field['value'] }}</textarea>
                                        @elseif ($field['type'] === 'boolean')
                                            <div class="flex items-center gap-3">
                                                <input type="hidden" name="settings[{{ $groupKey }}][{{ $fieldKey }}]" value="0">
                                                <input
                                                    id="{{ $groupKey }}_{{ $fieldKey }}"
                                                    type="checkbox"
                                                    name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value="1"
                                                    @checked($field['value'])
                                                    class="rounded border-gray-300 text-gray-900 shadow-sm"
                                                >
                                                <label for="{{ $groupKey }}_{{ $fieldKey }}" class="text-sm text-gray-700">
                                                    {{ $translate('Enabled') }}
                                                </label>
                                            </div>
                                        @elseif ($field['type'] === 'select')
                                            <select
                                                id="{{ $groupKey }}_{{ $fieldKey }}"
                                                name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                @disabled($groupKey === 'general' && $fieldKey === 'site_language' && ! ($isSuperAdmin ?? false))
                                            >
                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $field['value'] === (string) $optionValue)>
                                                        {{ $translate($optionLabel) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if ($groupKey === 'general' && $fieldKey === 'site_language' && ! ($isSuperAdmin ?? false))
                                                <p class="mt-1 text-xs text-amber-700">{{ $translate('Only a super admin can change the site language.') }}</p>
                                            @endif
                                        @elseif ($field['type'] === 'password')
                                            <input
                                                id="{{ $groupKey }}_{{ $fieldKey }}"
                                                type="password"
                                                name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                value=""
                                                autocomplete="new-password"
                                                placeholder="{{ ! empty($field['has_secret_value']) ? 'Encrypted password stored â€” leave blank to keep it' : 'Enter SMTP password' }}"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                @disabled($groupKey === 'general' && $fieldKey === 'site_language' && ! ($isSuperAdmin ?? false))
                                            >
                                        @elseif ($field['type'] === 'number')
                                            <input
                                                id="{{ $groupKey }}_{{ $fieldKey }}"
                                                type="number"
                                                name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                value="{{ $field['value'] }}"
                                                @if(isset($field['min_value']) && $field['min_value'] !== null) min="{{ $field['min_value'] }}" @endif
                                                @if(isset($field['max_value']) && $field['max_value'] !== null) max="{{ $field['max_value'] }}" @endif
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                @disabled($groupKey === 'general' && $fieldKey === 'site_language' && ! ($isSuperAdmin ?? false))
                                            >
                                        @elseif ($field['type'] === 'color')
                                            <div class="color-field">
                                                <input
                                                    id="{{ $groupKey }}_{{ $fieldKey }}"
                                                    type="color"
                                                    name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value="{{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $field['value']) ? $field['value'] : $field['default'] }}"
                                                    data-color-picker="{{ $groupKey }}_{{ $fieldKey }}"
                                                >
                                                <input
                                                    type="text"
                                                    value="{{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $field['value']) ? $field['value'] : $field['default'] }}"
                                                    class="rounded-md border-gray-300 shadow-sm"
                                                    data-color-text="{{ $groupKey }}_{{ $fieldKey }}"
                                                >
                                            </div>
                                        @elseif ($groupKey === 'general' && in_array($fieldKey, ['date_format', 'time_format'], true))
                                            @php
                                                $now = now();
                                                $isDateFormat = $fieldKey === 'date_format';
                                                $formatOptions = $isDateFormat
                                                    ? [
                                                        'F j, Y' => $now->format('F j, Y'),
                                                        'Y-m-d' => $now->format('Y-m-d'),
                                                        'm/d/Y' => $now->format('m/d/Y'),
                                                        'd/m/Y' => $now->format('d/m/Y'),
                                                        'd.m.Y' => $now->format('d.m.Y'),
                                                    ]
                                                    : [
                                                        'g:i a' => $now->format('g:i a'),
                                                        'g:i A' => $now->format('g:i A'),
                                                        'H:i' => $now->format('H:i'),
                                                    ];
                                                $currentFormat = (string) $field['value'];
                                                $customFormat = $isDateFormat
                                                    ? ($groups['general']['fields']['custom_date_format']['value'] ?? $currentFormat)
                                                    : ($groups['general']['fields']['custom_time_format']['value'] ?? $currentFormat);
                                                $customKey = $isDateFormat ? 'custom_date_format' : 'custom_time_format';
                                                $selectedIsCustom = ! array_key_exists($currentFormat, $formatOptions);
                                                $previewFormat = $selectedIsCustom ? $customFormat : $currentFormat;
                                            @endphp

                                            <div class="format-options" data-format-group="{{ $groupKey }}_{{ $fieldKey }}">
                                                @foreach ($formatOptions as $optionValue => $example)
                                                    <label class="format-option">
                                                        <input
                                                            type="radio"
                                                            name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                            value="{{ $optionValue }}"
                                                            @checked((string) $field['value'] === (string) $optionValue)
                                                            data-format-choice="{{ $groupKey }}_{{ $fieldKey }}"
                                                            data-format-preview="{{ $example }}"
                                                        >
                                                        <span class="format-example">{{ $example }}</span>
                                                        <code class="format-code">{{ $optionValue }}</code>
                                                    </label>
                                                @endforeach

                                                <label class="format-option">
                                                    <input
                                                        type="radio"
                                                        name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                        value="{{ $customFormat }}"
                                                        @checked($selectedIsCustom)
                                                        data-format-custom-radio="{{ $groupKey }}_{{ $fieldKey }}"
                                                        data-format-choice="{{ $groupKey }}_{{ $fieldKey }}"
                                                        data-format-preview="{{ $now->format($customFormat ?: ($isDateFormat ? 'F j, Y' : 'g:i a')) }}"
                                                    >
                                                    <span class="format-example">{{ $translate('Custom') }}:</span>
                                                    <input
                                                        type="text"
                                                        name="settings[{{ $groupKey }}][{{ $customKey }}]"
                                                        value="{{ $customFormat }}"
                                                        class="format-custom-input"
                                                        data-format-custom-input="{{ $groupKey }}_{{ $fieldKey }}"
                                                        data-format-hidden-target="{{ $groupKey }}_{{ $fieldKey }}"
                                                    >
                                                </label>

                                                <p class="format-preview">
                                                    <strong data-i18n="core.settings.preview">{{ __('core.settings.preview') }}</strong>
                                                    <span data-format-preview-output="{{ $groupKey }}_{{ $fieldKey }}">
                                                        {{ $now->format($previewFormat ?: ($isDateFormat ? 'F j, Y' : 'g:i a')) }}
                                                    </span>
                                                </p>
                                            </div>
                                        @elseif ($field['type'] === 'radio')
                                            <div class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                    <label class="flex items-center gap-3 text-sm text-gray-700">
                                                        <input
                                                            type="radio"
                                                            name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                            value="{{ $optionValue }}"
                                                            @checked((string) $field['value'] === (string) $optionValue)
                                                            class="border-gray-300 text-gray-900 shadow-sm"
                                                        >
                                                        <span>{{ $translate($optionLabel) }}</span>
                                                        <code class="rounded bg-gray-100 px-2 py-1 text-xs">{{ $optionValue }}</code>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($field['type'] === 'file')
                                            <div class="space-y-4">
                                                @php
                                                    $siteDisplayTitle = $groups['general']['fields']['site_title']['value'] ?? 'Z4Rank';
                                                    $isSiteLogoField = $groupKey === 'general' && $fieldKey === 'site_logo';
                                                    $isSiteIconField = $groupKey === 'general' && $fieldKey === 'site_icon';
                                                    $isBrandMediaField = $isSiteLogoField || $isSiteIconField;
                                                    $hasCustomFileValue = (bool) ($field['has_custom_value'] ?? false);
                                                @endphp
                                                <input
                                                    type="hidden"
                                                    name="remove_files[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value="0"
                                                    data-remove-input="{{ $groupKey }}.{{ $fieldKey }}"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="media[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value=""
                                                    data-media-input="{{ $groupKey }}.{{ $fieldKey }}"
                                                >
                                                @if ($field['value'])
                                                    @if ($isSiteLogoField)
                                                        <div class="site-logo-preview">
                                                            <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                        </div>
                                                    @elseif ($isSiteIconField)
                                                        <div class="site-icon-preview">
                                                            <div class="site-icon-large">
                                                                <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                            </div>
                                                            <div class="site-icon-browser">
                                                                <div class="site-icon-dots">
                                                                    <span></span>
                                                                    <span></span>
                                                                    <span></span>
                                                                </div>
                                                                <div class="site-icon-tab">
                                                                    <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                                    <span class="site-icon-tab-title">{{ $siteDisplayTitle }}</span>
                                                                    <span class="site-icon-tab-close">x</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="media-current">
                                                            <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                            @if ($hasCustomFileValue)
                                                                <button
                                                                    type="button"
                                                                    class="media-remove"
                                                                    title="Remove"
                                                                    aria-label="Remove {{ $translate($field['label']) }}"
                                                                    data-remove-media="{{ $groupKey }}.{{ $fieldKey }}"
                                                                >x</button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif

                                                <input
                                                    id="{{ $groupKey }}_{{ $fieldKey }}"
                                                    type="file"
                                                    name="files[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    accept=".png,.jpg,.jpeg,.webp,.ico"
                                                    class="sr-only"
                                                    data-file-input="{{ $groupKey }}.{{ $fieldKey }}"
                                                >
                                                <div class="{{ $isSiteLogoField && $field['value'] ? 'site-logo-actions' : ($isSiteIconField && $field['value'] ? 'site-icon-actions' : '') }}">
                                                    <button
                                                        type="button"
                                                        class="media-button {{ $isBrandMediaField && $field['value'] ? 'media-button-primary' : '' }}"
                                                        data-open-media="{{ $groupKey }}.{{ $fieldKey }}"
                                                        data-open-media-label="{{ $translate($field['label']) }}"
                                                    >
                                                        @if ($isSiteLogoField && $field['value'])
                                                            {{ $translate('Change Site Logo') }}
                                                        @elseif ($isSiteIconField && $field['value'])
                                                            {{ $translate('Change Site Icon') }}
                                                        @else
                                                            {{ $translate('Add Media') }}
                                                        @endif
                                                    </button>
                                                    @if ($isBrandMediaField && $field['value'] && $hasCustomFileValue)
                                                        <button
                                                            type="button"
                                                            class="media-button media-button-danger"
                                                            data-remove-media="{{ $groupKey }}.{{ $fieldKey }}"
                                                        >
                                                            {{ $translate($isSiteLogoField ? 'Remove Site Logo' : 'Remove Site Icon') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <x-text-input
                                                :id="$groupKey.'_'.$fieldKey"
                                                :type="$field['type'] === 'url' ? 'url' : ($field['type'] === 'email' ? 'email' : 'text')"
                                                :name="'settings['.$groupKey.']['.$fieldKey.']'"
                                                :value="$field['value']"
                                                class="block w-full focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

<section class="overflow-hidden bg-white shadow-sm sm:rounded-lg" data-settings-panel="language-translations" hidden>
                <div class="border-b border-gray-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('core.settings.translations') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('core.settings.translations_help') }}</p>
                </div>
                <div class="px-6 py-5">
                    <a href="{{ route('admin.translations.index') }}" class="inline-flex rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700">{{ __('core.settings.open') }}</a>
                </div>
            </section>

                <div class="flex justify-end">
                    <x-primary-button>{{ $translate('Save Changes') }}</x-primary-button>
                </div>
            </form>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg" data-settings-panel="core-updates" hidden>
                <div class="border-b border-gray-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('core.updates.title') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('core.updates.help') }}</p>
                </div>

                <div class="space-y-5 px-6 py-5">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700">
                            {{ __('core.updates.current_version') }}: {{ $coreUpdates['version'] ?? '—' }}
                        </span>
                        @if (! empty($coreUpdates['last']))
                            <span class="text-gray-500">
                                {{ __('core.updates.last_update') }}: {{ $coreUpdates['last']['at'] ?? '' }}
                                · {{ $coreUpdates['last']['file'] ?? '' }}
                            </span>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.settings.core-update') }}" enctype="multipart/form-data" class="space-y-4 rounded-lg border border-gray-200 p-4">
                        @csrf
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-900" for="core-update-package">{{ __('core.updates.choose_file') }}</label>
                            <input id="core-update-package" type="file" name="package" accept=".zip" required class="block w-full rounded-md border border-gray-300 p-2 text-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('core.updates.file_help') }}</p>
                        </div>

                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="confirm" value="1" required class="mt-1 rounded border-gray-300">
                            <span>{{ __('core.updates.confirm') }}</span>
                        </label>

                        <button type="submit" class="inline-flex rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">
                            {{ __('core.updates.apply') }}
                        </button>
                    </form>


                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-gray-900">{{ __('core.updates.history') }}</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ __('core.updates.col_date') }}</th>
                                        <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ __('core.updates.col_file') }}</th>
                                        <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ __('core.updates.col_files') }}</th>
                                        <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ __('core.updates.col_status') }}</th>
                                        <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ __('core.updates.col_message') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse (($coreUpdates['history'] ?? []) as $entry)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-600">{{ $entry['at'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $entry['file'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $entry['files'] ?? 0 }}</td>
                                            <td class="px-3 py-2">
                                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ($entry['status'] ?? '') === 'success' ? 'bg-green-50 text-green-700' : (($entry['status'] ?? '') === 'rolled-back' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">
                                                    {{ $entry['status'] ?? '' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600">{{ $entry['message'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">{{ __('core.updates.empty') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="smtp-test-card" data-settings-mail-test hidden>
                @csrf
                <label>
                    <span class="mb-2 block text-sm font-semibold text-gray-900" data-i18n="core.settings.send_test_email">{{ __('core.settings.send_test_email') }}</span>
                    <input type="email" name="test_email" value="{{ old('test_email', data_get($groups, 'general.fields.admin_email.value', '')) }}" required class="block w-full rounded-md border-gray-300 shadow-sm">
                    <small class="mt-2 block text-gray-500" data-i18n="core.settings.send_test_hint">{{ __('core.settings.send_test_hint') }}</small>
                </label>
                <button type="submit" class="rounded-md border border-blue-600 bg-white px-4 py-2 text-sm font-semibold text-blue-700" data-i18n="core.settings.send_test">{{ __('core.settings.send_test') }}</button>
            </form>

            <div class="media-modal" data-media-modal hidden>
                <div class="media-modal-panel">
                    <div class="media-modal-header">
                        <h3 data-media-modal-title>{{ $translate('Add Media') }}</h3>
                        <button type="button" class="media-close" data-close-media aria-label="Close">x</button>
                    </div>

                    <div class="media-modal-tabs">
                        <button type="button" class="settings-tab is-active" data-media-tab="upload">{{ $translate('Upload files') }}</button>
                        <button type="button" class="settings-tab" data-media-tab="library">{{ $translate('Media Library') }}</button>
                    </div>

                    <div class="media-modal-body">
                        <div class="media-upload-panel" data-media-panel="upload">
                            <div class="media-upload-title">{{ $translate('Drop files to upload') }}</div>
                            <div class="text-sm text-gray-600">{{ $translate('or') }}</div>
                            <button type="button" class="media-button" data-trigger-upload>{{ $translate('Select Files') }}</button>
                            <p class="text-sm text-gray-600">{{ $translate('Suggested image dimensions: 512 by 512 pixels.') }}</p>
                        </div>

                        <div data-media-panel="library" hidden>
                            @if (! empty($mediaLibrary))
                                <div class="media-library-layout">
                                    <div class="media-library-grid">
                                        @foreach ($mediaLibrary as $media)
                                            <button
                                                type="button"
                                                class="media-library-item"
                                                data-preview-media="{{ $media['url'] }}"
                                                data-media-alt="{{ $media['metadata']['alt_text'] ?? '' }}"
                                                data-media-title="{{ $media['metadata']['title'] ?? '' }}"
                                                data-media-caption="{{ $media['metadata']['caption'] ?? '' }}"
                                                data-media-description="{{ $media['metadata']['description'] ?? '' }}"
                                            >
                                                <img src="{{ $media['url'] }}" alt="{{ $media['metadata']['alt_text'] ?: 'Media image' }}">
                                            </button>
                                        @endforeach
                                    </div>

                                    <form method="POST" action="{{ route('admin.settings.media.update') }}" class="media-details" data-media-details hidden>
                                        @csrf
                                        @method('PATCH')

                                        <input type="hidden" name="media_url" value="" data-media-url-input>

                                        <div class="media-details-preview">
                                            <img src="" alt="" data-media-details-image>
                                        </div>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Alternative Text') }}</span>
                                            <textarea name="alt_text" rows="2" data-media-alt-input></textarea>
                                        </label>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Title') }}</span>
                                            <input type="text" name="title" data-media-title-input>
                                        </label>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Caption') }}</span>
                                            <textarea name="caption" rows="2" data-media-caption-input></textarea>
                                        </label>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Description') }}</span>
                                            <textarea name="description" rows="3" data-media-description-input></textarea>
                                        </label>

                                        <div class="media-details-actions">
                                            <x-primary-button>{{ $translate('Save SEO') }}</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            @else
                                <p class="text-sm text-gray-600">{{ $translate('No images are available in the media library yet.') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="media-modal-footer">
                        <button type="button" class="media-set-button" data-use-selected-media disabled>{{ $translate('Set Image') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
            const settingsForm = document.querySelector('form[action="{{ route('admin.settings.update') }}"]');
            const smtpTestForm = document.querySelector('[data-settings-mail-test]');
            const mediaModal = document.querySelector('[data-media-modal]');
            const mediaModalTitle = document.querySelector('[data-media-modal-title]');
            const mediaSetButton = document.querySelector('[data-use-selected-media]');
            const mediaDetails = document.querySelector('[data-media-details]');
            const mediaUrlInput = document.querySelector('[data-media-url-input]');
            const mediaDetailsImage = document.querySelector('[data-media-details-image]');
            const mediaAltInput = document.querySelector('[data-media-alt-input]');
            const mediaTitleInput = document.querySelector('[data-media-title-input]');
            const mediaCaptionInput = document.querySelector('[data-media-caption-input]');
            const mediaDescriptionInput = document.querySelector('[data-media-description-input]');
            const mediaTabs = Array.from(document.querySelectorAll('[data-media-tab]'));
            const mediaPanels = Array.from(document.querySelectorAll('[data-media-panel]'));
            const mediaCopy = {
                choosePrefix: @json($translate('Choose a')),
                setAsPrefix: @json($translate('Set as')),
            };
            let activeMediaKey = null;
            let selectedMediaUrl = null;

            const activateTab = (activeKey) => {
                tabs.forEach((tab) => {
                    tab.classList.toggle('is-active', tab.dataset.settingsTab === activeKey);
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.settingsPanel !== activeKey;
                });

                if (settingsForm) {
                    settingsForm.dataset.activeSettingsTab = activeKey;
                    settingsForm.action = settingsForm.action.split('#')[0] + `#settings-${activeKey}`;
                }

                if (smtpTestForm) {
                    smtpTestForm.hidden = activeKey !== 'mail';
                }

                history.replaceState(null, '', `#settings-${activeKey}`);
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => activateTab(tab.dataset.settingsTab));
            });

            const tabParam = new URLSearchParams(window.location.search).get('tab') || '';
            const requestedTab = tabParam !== '' ? tabParam : window.location.hash.replace('#settings-', '');
            const hasRequestedTab = tabs.some((tab) => tab.dataset.settingsTab === requestedTab);

            if (hasRequestedTab) {
                activateTab(requestedTab);
            } else if (tabs[0]) {
                activateTab(tabs[0].dataset.settingsTab);
            }

            const pad = (value) => String(value).padStart(2, '0');
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December',
            ];

            const formatPreviewDate = (format) => {
                const date = new Date();
                const hours = date.getHours();
                const hour12 = hours % 12 || 12;
                const replacements = {
                    F: monthNames[date.getMonth()],
                    j: String(date.getDate()),
                    Y: String(date.getFullYear()),
                    m: pad(date.getMonth() + 1),
                    d: pad(date.getDate()),
                    H: pad(hours),
                    g: String(hour12),
                    i: pad(date.getMinutes()),
                    a: hours >= 12 ? 'pm' : 'am',
                    A: hours >= 12 ? 'PM' : 'AM',
                };

                return String(format || '')
                    .split('')
                    .map((token) => replacements[token] ?? token)
                    .join('');
            };

            document.querySelectorAll('[data-format-choice]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    const output = document.querySelector(`[data-format-preview-output="${radio.dataset.formatChoice}"]`);

                    if (output) {
                        output.textContent = radio.dataset.formatPreview || formatPreviewDate(radio.value);
                    }
                });
            });

            document.querySelectorAll('[data-format-custom-input]').forEach((input) => {
                const formatKey = input.dataset.formatCustomInput;
                const customRadio = document.querySelector(`[data-format-custom-radio="${formatKey}"]`);
                const output = document.querySelector(`[data-format-preview-output="${formatKey}"]`);

                const updateCustomFormat = () => {
                    if (customRadio) {
                        customRadio.value = input.value;
                        customRadio.dataset.formatPreview = formatPreviewDate(input.value);
                        customRadio.checked = true;
                    }

                    if (output) {
                        output.textContent = formatPreviewDate(input.value);
                    }
                };

                input.addEventListener('focus', updateCustomFormat);
                input.addEventListener('input', updateCustomFormat);
            });

            document.querySelectorAll('[data-color-picker]').forEach((picker) => {
                const text = document.querySelector(`[data-color-text="${picker.dataset.colorPicker}"]`);

                if (!text) {
                    return;
                }

                picker.addEventListener('input', () => {
                    text.value = picker.value;
                });

                text.addEventListener('input', () => {
                    if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) {
                        picker.value = text.value;
                    }
                });
            });

            const submitSettings = () => {
                if (settingsForm) {
                    const activeTab = settingsForm.dataset.activeSettingsTab || tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.settingsTab || tabs[0]?.dataset.settingsTab;

                    if (activeTab) {
                        settingsForm.action = settingsForm.action.split('#')[0] + `#settings-${activeTab}`;
                    }

                    settingsForm.submit();
                }
            };

            if (settingsForm) {
                settingsForm.addEventListener('submit', () => {
                    const activeTab = settingsForm.dataset.activeSettingsTab || tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.settingsTab || tabs[0]?.dataset.settingsTab;

                    if (activeTab) {
                        settingsForm.action = settingsForm.action.split('#')[0] + `#settings-${activeTab}`;
                    }
                });
            }

            const openMediaModal = (mediaKey, label) => {
                activeMediaKey = mediaKey;
                selectedMediaUrl = null;
                mediaModalTitle.textContent = `${mediaCopy.choosePrefix} ${label}`;
                mediaSetButton.textContent = `${mediaCopy.setAsPrefix} ${label}`;
                mediaSetButton.disabled = true;
                mediaDetails.hidden = true;
                document.querySelectorAll('[data-preview-media]').forEach((button) => button.classList.remove('is-selected'));
                document.body.style.overflow = 'hidden';
                mediaModal.hidden = false;
            };

            const closeMediaModal = () => {
                mediaModal.hidden = true;
                activeMediaKey = null;
                selectedMediaUrl = null;
                document.body.style.overflow = '';
            };

            const activateMediaTab = (activeKey) => {
                mediaTabs.forEach((tab) => {
                    tab.classList.toggle('is-active', tab.dataset.mediaTab === activeKey);
                });

                mediaPanels.forEach((panel) => {
                    panel.hidden = panel.dataset.mediaPanel !== activeKey;
                });
            };

            document.querySelectorAll('[data-open-media]').forEach((button) => {
                button.addEventListener('click', () => openMediaModal(button.dataset.openMedia, button.dataset.openMediaLabel || 'Image'));
            });

            document.querySelectorAll('[data-close-media]').forEach((button) => {
                button.addEventListener('click', closeMediaModal);
            });

            mediaModal.addEventListener('click', (event) => {
                if (event.target === mediaModal) {
                    closeMediaModal();
                }
            });

            mediaTabs.forEach((tab) => {
                tab.addEventListener('click', () => activateMediaTab(tab.dataset.mediaTab));
            });

            document.querySelectorAll('[data-remove-media]').forEach((button) => {
                button.addEventListener('click', () => {
                    const removeInput = document.querySelector(`[data-remove-input="${button.dataset.removeMedia}"]`);

                    if (removeInput) {
                        removeInput.value = '1';
                        submitSettings();
                    }
                });
            });

            document.querySelectorAll('[data-file-input]').forEach((input) => {
                input.addEventListener('change', () => {
                    if (input.files.length > 0) {
                        submitSettings();
                    }
                });
            });

            document.querySelector('[data-trigger-upload]').addEventListener('click', () => {
                const fileInput = document.querySelector(`[data-file-input="${activeMediaKey}"]`);

                if (fileInput) {
                    fileInput.click();
                }
            });

            document.querySelectorAll('[data-preview-media]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectedMediaUrl = button.dataset.previewMedia;

                    document.querySelectorAll('[data-preview-media]').forEach((item) => item.classList.remove('is-selected'));
                    button.classList.add('is-selected');

                    mediaUrlInput.value = selectedMediaUrl;
                    mediaDetailsImage.src = selectedMediaUrl;
                    mediaDetailsImage.alt = button.dataset.mediaAlt || '';
                    mediaAltInput.value = button.dataset.mediaAlt || '';
                    mediaTitleInput.value = button.dataset.mediaTitle || '';
                    mediaCaptionInput.value = button.dataset.mediaCaption || '';
                    mediaDescriptionInput.value = button.dataset.mediaDescription || '';
                    mediaSetButton.disabled = false;
                    mediaDetails.hidden = false;
                });
            });

            mediaSetButton.addEventListener('click', () => {
                const mediaInput = document.querySelector(`[data-media-input="${activeMediaKey}"]`);

                if (mediaInput && selectedMediaUrl) {
                    mediaInput.value = selectedMediaUrl;
                    submitSettings();
                }
            });
        });
    </script>
</x-app-layout>

