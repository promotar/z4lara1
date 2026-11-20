@push('styles')
    <link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
    <main class="z4-exhibitions-admin" data-ve-published-artworks>
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_3abf194d6c') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_d50805ca2a') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_6e005b306b') }}</p>
                </div>
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            <form class="z4-ve-filter" method="get">
                <label>
                    {{ __('exhibitions::ui.auto_b591065558') }}
                    <input name="q" value="{{ $search }}" placeholder="{{ __('exhibitions::ui.auto_a0751c7f60') }}">
                </label>
                <button type="submit">{{ __('exhibitions::ui.auto_13348442cc') }}</button>
                @if($search !== '')
                    <a href="{{ route('admin.plugins.exhibitions.virtual.artworks.index') }}">{{ __('exhibitions::ui.auto_dc30bc0c79') }}</a>
                @endif
            </form>

            <section class="z4-exhibitions-panel">
                <div class="z4-exhibitions-section-title">
                    <span>01</span>
                    <div>
                        <h2>{{ __('exhibitions::ui.auto_82351d25ab') }}</h2>
                        <p>{{ __('exhibitions::ui.auto_4576296f46') }}</p>
                    </div>
                </div>
                <div class="z4-table-scroll">
                    <table class="z4-exhibitions-table z4-ve-published-table is-current">
                        <thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_4047c5607d') }}</th><th>{{ __('exhibitions::ui.auto_a8f14fa92f') }}</th><th>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}</th><th>{{ __('exhibitions::ui.auto_398537a128') }}</th></tr></thead>
                        <tbody>
                            @forelse($directPlacements as $placement)
                                <tr>
                                    <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $placement->artwork_title }}</strong></td>
                                    <td data-label="{{ __('exhibitions::ui.data_artist') }}">{{ $placement->artistic_name ?: $placement->legal_name }}</td>
                                    <td data-label="{{ __('exhibitions::ui.data_exhibition_display') }}">{{ $placement->environment_title }}<br><small>{{ $placement->room_title }} · {{ $placement->slot_name }} · {{ $placement->slot_code }}</small></td>
                                    <td data-label="{{ __('exhibitions::ui.data_status') }}">
                                        <span @class(['z4-exhibitions-status', 'is-ready' => !$placement->is_sold, 'is-pending' => $placement->is_sold])>
                                            {{ $placement->is_sold ? 'Sold · retained' : 'Published' }}
                                        </span>
                                    </td>
                                    <td data-label="{{ __('exhibitions::ui.data_administrative_action') }}">
                                        @if(!$placement->is_sold)
                                            <form class="z4-ve-admin-remove" method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.placements.remove', $placement->public_id) }}" onsubmit="return confirm('Remove this artwork from the virtual exhibition? The artist will see the reason.');">
                                                @csrf
                                                <label>
                                                    <span class="z4-visually-hidden">{{ __('exhibitions::ui.auto_9db207e74a') }}</span>
                                                    <input name="reason" required minlength="8" maxlength="500" placeholder="{{ __('exhibitions::ui.auto_b0be400390') }}">
                                                </label>
                                                <button class="z4-exhibitions-button is-danger" type="submit">{{ __('exhibitions::ui.auto_1063e38cb5') }}</button>
                                            </form>
                                        @else
                                            <span>{{ __('exhibitions::ui.auto_9693491ef8') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ __('exhibitions::ui.auto_d27e9a5df0') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-platform-pagination :paginator="$directPlacements" page-name="current_page" />
            </section>

            <section class="z4-exhibitions-panel">
                <div class="z4-exhibitions-section-title">
                    <span>02</span>
                    <div>
                        <h2>{{ __('exhibitions::ui.auto_d99f6690a6') }}</h2>
                        <p>{{ __('exhibitions::ui.auto_0feca09a31') }}</p>
                    </div>
                </div>
                <div class="z4-table-scroll">
                    <table class="z4-exhibitions-table z4-ve-published-table is-legacy">
                        <thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_4047c5607d') }}</th><th>{{ __('exhibitions::ui.auto_07360e4453') }}</th><th>{{ __('exhibitions::ui.auto_9b9d8a976b') }}</th><th>{{ __('exhibitions::ui.auto_004bf6c9a4') }}</th></tr></thead>
                        <tbody>
                            @forelse($legacyPlacements as $placement)
                                <tr>
                                    <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $placement->artwork_title }}</strong></td>
                                    <td data-label="{{ __('exhibitions::ui.data_artist') }}">{{ $placement->artistic_name ?: $placement->legal_name }}</td>
                                    <td data-label="{{ __('exhibitions::ui.data_room_display') }}">{{ $placement->room_title }}<br><small>{{ $placement->slot_code }} · {{ $placement->slot_name }}</small></td>
                                    <td data-label="{{ __('exhibitions::ui.data_published') }}">{{ $placement->published_at ?: 'Published' }}</td>
                                    <td data-label="{{ __('exhibitions::ui.data_action') }}"><a href="{{ route('admin.plugins.exhibitions.virtual.requests.review', $placement->public_id) }}">{{ __('exhibitions::ui.auto_0da9511b88') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ __('exhibitions::ui.auto_bf43cb279f') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-platform-pagination :paginator="$legacyPlacements" page-name="legacy_page" />
            </section>
        </div>
    </main>
</x-app-layout>
