<x-app-layout>
    <x-slot name="header">
        <div class="admin-theme-settings-heading">
            <div>
                <span>Appearance</span>
                <h2>Admin Theme</h2>
            </div>

            <a href="{{ route('admin.plugins.index') }}">Back to plugins</a>
        </div>
    </x-slot>

    <div class="admin-theme-settings-page">
        @if (session('status'))
            <div class="admin-theme-settings-notice">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="admin-theme-settings-notice admin-theme-settings-notice--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.plugins.admin-theme.settings.update') }}">
            @csrf
            @method('PATCH')

            <section class="admin-theme-settings-panel">
                <div class="admin-theme-settings-status">
                    <span class="admin-theme-settings-mark">A</span>
                    <div>
                        <h3>Art INPA Admin Theme</h3>
                        <p>Changes are applied across the administration dashboard after saving.</p>
                    </div>
                    <strong>Version {{ $themeVersion }}</strong>
                </div>

                <div class="admin-theme-settings-grid">
                    @foreach ([
                        'sidebar_width',
                        'sidebar_background',
                        'sidebar_text_color',
                        'active_menu_color',
                        'primary_color',
                        'page_background',
                        'card_background',
                        'card_padding',
                        'card_margin',
                        'border_color',
                        'border_size',
                        'font_family',
                        'base_font_size',
                        'border_radius',
                        'content_padding',
                        'header_height',
                    ] as $key)
                        @php
                            $definition = $definitions[$key];
                            $value = old($key, $values[$key]);
                        @endphp

                        <label class="admin-theme-setting-field">
                            <span>{{ $definition['label'] }}</span>

                            @if ($definition['type'] === 'color')
                                <span class="admin-theme-color-control">
                                    <input type="color" name="{{ $key }}" value="{{ $value }}">
                                    <output>{{ $value }}</output>
                                </span>
                            @elseif ($definition['type'] === 'select')
                                <select name="{{ $key }}">
                                    @foreach ($definition['options'] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected($value === $optionValue)>
                                            {{ $optionLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span class="admin-theme-number-control">
                                    <input
                                        type="number"
                                        name="{{ $key }}"
                                        value="{{ $value }}"
                                        min="{{ $definition['min'] }}"
                                        max="{{ $definition['max'] }}"
                                        step="1"
                                    >
                                    <small>{{ $definition['unit'] }}</small>
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>

                <label class="admin-theme-css-field">
                    <span>Custom CSS editor</span>
                    <textarea
                        name="custom_css"
                        rows="14"
                        spellcheck="false"
                        placeholder="/* Add administration-only CSS overrides here. */"
                    >{{ old('custom_css', $values['custom_css']) }}</textarea>
                </label>

                <div class="admin-theme-settings-actions">
                    <button type="submit">Save changes</button>
                    <span>All numeric layout values use pixels.</span>
                </div>
            </section>
        </form>

        <form
            method="POST"
            action="{{ route('admin.plugins.admin-theme.settings.reset') }}"
            class="admin-theme-reset-form"
            onsubmit="return confirm('Restore all admin theme settings to their defaults?')"
        >
            @csrf
            @method('DELETE')
            <button type="submit">Restore defaults</button>
        </form>
    </div>

    <script>
        document.querySelectorAll('.admin-theme-color-control input[type="color"]').forEach((input) => {
            input.addEventListener('input', () => {
                input.nextElementSibling.textContent = input.value.toUpperCase();
            });
        });
    </script>
</x-app-layout>
