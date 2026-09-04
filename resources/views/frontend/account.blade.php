@push('styles')
    @vite('resources/css/account.css')
@endpush

<x-frontend-layout>
    @php
        $initial = function_exists('mb_substr')
            ? mb_substr((string) $user->name, 0, 1, 'UTF-8')
            : substr((string) $user->name, 0, 1);
        $defaultSection = $sections[0]['key'] ?? 'overview';
    @endphp

    <section class="ainpa-account-page" dir="auto">
        <div class="ainpa-account-shell">
            <div class="ainpa-account-workspace" x-data="{ activeSection: @js($defaultSection) }">
                <aside class="ainpa-account-sidebar">
                    <div class="ainpa-account-profile-card">
                        <div class="ainpa-account-avatar" aria-hidden="true">{{ $initial }}</div>
                        <div class="ainpa-account-profile-card__content">
                            <p>Signed in as</p>
                            <h2>{{ $user->name }}</h2>
                            <span>{{ $user->email }}</span>
                        </div>
                    </div>

                    <div class="ainpa-account-tabs" role="tablist" aria-label="Account sections">
                        @foreach ($sections as $section)
                            <button
                                type="button"
                                role="tab"
                                id="account-tab-{{ $section['key'] }}"
                                :class="{ 'is-active': activeSection === @js($section['key']) }"
                                :aria-selected="(activeSection === @js($section['key'])).toString()"
                                aria-controls="account-panel-{{ $section['key'] }}"
                                @click="activeSection = @js($section['key'])"
                            >
                                <span class="ainpa-account-tab-icon" aria-hidden="true">{{ $section['icon'] }}</span>
                                <span>{{ $section['label'] }}</span>
                                @if ($section['badge'] !== null)<b>{{ $section['badge'] }}</b>@endif
                            </button>
                        @endforeach
                    </div>

                    <div class="ainpa-account-sidebar__actions">
                        <form method="POST" action="{{ route('logout') }}" class="ainpa-account-logout">
                            @csrf
                            <button type="submit" class="ainpa-account-button ainpa-account-button--ghost">Log Out</button>
                        </form>
                    </div>
                </aside>

                <main class="ainpa-account-tab-panels">
                    @foreach ($sections as $section)
                        <section
                            x-cloak
                            x-show="activeSection === @js($section['key'])"
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
</x-frontend-layout>
