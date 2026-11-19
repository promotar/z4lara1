@push('styles')
    @vite('resources/css/account.css')
    <style>
        .ainpa-account-tab-panels > section[hidden]{display:none!important}
        .ainpa-account-language{display:flex;flex-direction:column;gap:.4rem;padding:.85rem .9rem;border-top:1px solid rgba(255,255,255,.15)}
        .ainpa-account-language>span{color:#fff;font-size:.68rem;font-weight:800;letter-spacing:.08em;opacity:.7;text-transform:uppercase}
        .ainpa-account-language .platform-locale-switch{color:#fff!important;font-size:.8rem}
        .ainpa-account-language .platform-locale-switch a,
        .ainpa-account-language .platform-locale-switch a:visited,
        .ainpa-account-language .platform-locale-switch a:hover,
        .ainpa-account-language .platform-locale-switch a:focus{color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:.65;text-decoration:none;background:transparent!important;padding:.1rem .15rem}
        .ainpa-account-language .platform-locale-switch a.is-active{color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1;font-weight:800;text-decoration:underline;text-underline-offset:3px;background:transparent!important}
        .ainpa-account-language .platform-locale-switch__divider{color:#fff!important;opacity:.3}
    </style>
@endpush

<x-frontend-layout>
    @php
        $initial = function_exists('mb_substr')
            ? mb_substr((string) $user->name, 0, 1, 'UTF-8')
            : substr((string) $user->name, 0, 1);
        $requestedSection = trim((string) request()->query('section', ''));
        $sectionKeys = array_map(static fn (array $section): string => (string) $section['key'], $sections);
        $defaultSection = in_array($requestedSection, $sectionKeys, true)
            ? $requestedSection
            : ($sections[0]['key'] ?? 'overview');
    @endphp

    <section class="ainpa-account-page" dir="auto">
        <div class="ainpa-account-shell">
            <div class="ainpa-account-workspace" data-account-section="{{ $defaultSection }}">
                <aside class="ainpa-account-sidebar">
                    <div class="ainpa-account-profile-card">
                        <div class="ainpa-account-avatar" aria-hidden="true">{{ $initial }}</div>
                        <div class="ainpa-account-profile-card__content">
                            <p data-i18n="core.account.signed_in_as">{{ __('core.account.signed_in_as') }}</p>
                            <h2>{{ $user->name }}</h2>
                            <span>{{ $user->email }}</span>
                        </div>
                    </div>

                    <div class="ainpa-account-tabs" role="tablist" aria-label="{{ __('core.account.sections') }}">
                        @foreach ($sections as $section)
                            <button
                                type="button"
                                role="tab"
                                id="account-tab-{{ $section['key'] }}"
                                class="{{ $defaultSection === $section['key'] ? 'is-active' : '' }}"
                                aria-selected="{{ $defaultSection === $section['key'] ? 'true' : 'false' }}"
                                aria-controls="account-panel-{{ $section['key'] }}"
                                data-account-target="{{ $section['key'] }}"
                            >
                                <span class="ainpa-account-tab-icon" aria-hidden="true">{{ $section['icon'] }}</span>
                                <span>{{ $section['label'] }}</span>
                                @if ($section['badge'] !== null)<b>{{ $section['badge'] }}</b>@endif
                            </button>
                        @endforeach
                    </div>

                    <div class="ainpa-account-language" title="{{ __('core.common.language') }}">
                        <span>{{ __('core.common.language') }}</span>
                        @include('platform.language-switcher')
                    </div>

                    <div class="ainpa-account-sidebar__actions">
                        <form method="POST" action="{{ route('logout') }}" class="ainpa-account-logout">
                            @csrf
                            <button type="submit" class="ainpa-account-button ainpa-account-button--ghost" data-i18n="core.common.log_out">{{ __('core.common.log_out') }}</button>
                        </form>
                    </div>
                </aside>

                <main class="ainpa-account-tab-panels">
                    @foreach ($sections as $section)
                        <section
                            @if ($defaultSection !== $section['key']) hidden @endif
                            id="account-panel-{{ $section['key'] }}"
                            role="tabpanel"
                            tabindex="0"
                            aria-labelledby="account-tab-{{ $section['key'] }}"
                        >
                            @include($section['view'], array_merge($section['data'], [
                                'user' => $user,
                                'canAccessDashboard' => $canAccessDashboard,
                            ]))
                        </section>
                    @endforeach
                </main>
            </div>
        </div>
    </section>

    <script id="account-tab-switcher">
        (function () {
            var workspace = document.querySelector('.ainpa-account-workspace');
            if (!workspace) { return; }

            var tabs = [].slice.call(workspace.querySelectorAll('[data-account-target]'));
            var panels = [].slice.call(workspace.querySelectorAll('[id^="account-panel-"]'));

            function activate(key, updateHash) {
                if (!key) { return; }
                var panel = document.getElementById('account-panel-' + key);
                if (!panel) { return; }

                tabs.forEach(function (tab) {
                    var on = tab.getAttribute('data-account-target') === key;
                    tab.classList.toggle('is-active', on);
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                panels.forEach(function (p) { p.hidden = (p.id !== 'account-panel-' + key); });
                workspace.setAttribute('data-account-section', key);

                if (updateHash && window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + key);
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activate(tab.getAttribute('data-account-target'), true);
                });
            });

            function fromHash() {
                var key = (window.location.hash || '').replace(/^#/, '');
                if (key) { activate(key, false); }
            }

            window.addEventListener('hashchange', fromHash);
            document.addEventListener('DOMContentLoaded', fromHash);
            fromHash();
        })();
    </script>
</x-frontend-layout>

