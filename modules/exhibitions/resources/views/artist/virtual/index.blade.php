@push('styles')
    <link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}">
@endpush

<x-frontend-layout>
    <main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-virtual-exhibition>
        <div class="z4-exhibitions-container">
            @include('exhibitions::artist.dashboard._nav', [
                'title' => __('exhibitions::ui.artist_nav_virtual'),
                'subtitle' => __('exhibitions::ui.artist_page_virtual_subtitle'),
            ])

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif
            @if(isset($unavailable))
                <div class="z4-exhibitions-alert is-warning">
                    <strong>{{ __('exhibitions::ui.auto_181fe37750') }}</strong>
                    <p>{{ $unavailable }}</p>
                </div>
            @endif

            @if($virtualSection === 'available')
                <section class="z4-exhibitions-panel">
                    <div class="z4-exhibitions-section-title">
                        <span>01</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_8c83ffde6c') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_983555ef3e') }}</p>
                        </div>
                    </div>
                    @if($invalidDimensionArtworks->isNotEmpty())
                        <div class="z4-exhibitions-alert is-warning" role="alert">
                            <strong>{{ __('exhibitions::ui.auto_6b1035e7d2') }}</strong>
                            <p>{{ __('exhibitions::ui.auto_b7fa488978') }}</p>
                            <ul>
                                @foreach($invalidDimensionArtworks as $artwork)
                                    <li>
                                        <a href="{{ route('exhibitions.dashboard.artworks.show', $artwork->public_id) }}">{{ $artwork->title }}</a>
                                        — {{ $artwork->placement_dimensions_message }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($artworks->isNotEmpty() && $environments->isNotEmpty())
                        @foreach($environments as $placementEnvironment)
                            <div class="z4-ve-placement-entry">
                                <div>
                                    <strong>{{ $placementEnvironment->title }}</strong>
                                    <span class="z4-ve-hall-capacity">Capacity: {{ $placementEnvironment->artwork_capacity }} artworks</span>
                                    <p>{{ $placementEnvironment->available_artwork_count }} artwork(s) available for placement in this hall.</p>
                                </div>
                                @if($placementEnvironment->available_artwork_count > 0)
                                    <a class="z4-exhibitions-button" href="{{ route('exhibitions.dashboard.virtual.place', ['hall' => $placementEnvironment->public_id]) }}">{{ __('exhibitions::ui.auto_6f7fcf6471') }}</a>
                                @else
                                    <span class="z4-exhibitions-status">{{ __('exhibitions::ui.auto_c80f5048fd') }}</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="z4-exhibitions-empty">
                            <strong>{{ __('exhibitions::ui.auto_8161cbb3bd') }}</strong>
                            <p>{{ __('exhibitions::ui.auto_41de441f38') }}</p>
                        </div>
                    @endif
                </section>
            @elseif($virtualSection === 'removed')
                <section class="z4-exhibitions-panel">
                    <div class="z4-exhibitions-section-title">
                        <span>03</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_786706dbca') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_8cf2bc13dd') }}</p>
                        </div>
                    </div>
                    <div class="z4-table-scroll">
                        <table class="z4-exhibitions-table z4-ve-artist-table is-removed">
                            <thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_2aaf7dedf4') }}</th><th>{{ __('exhibitions::ui.auto_93f07b720e') }}</th><th>{{ __('exhibitions::ui.auto_9db207e74a') }}</th><th>{{ __('exhibitions::ui.auto_b2a3a87ef5') }}</th></tr></thead>
                            <tbody>
                                @forelse($adminRemovedPlacements as $placement)
                                    <tr>
                                        <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $placement->artwork_title }}</strong></td>
                                        <td data-label="Previous display">{{ $placement->environment_title }}<br><small>{{ $placement->room_title }} · {{ $placement->slot_name }}</small></td>
                                        <td data-label="Removed">
                                            @if($placement->release_occurred_at)
                                                <time datetime="{{ \Illuminate\Support\Carbon::parse($placement->release_occurred_at)->toAtomString() }}">
                                                    {{ \Illuminate\Support\Carbon::parse($placement->release_occurred_at)->format('Y-m-d H:i') }}
                                                </time>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td data-label="Removal reason"><p class="z4-ve-removal-reason">{{ $placement->release_reason ?: 'No reason was recorded.' }}</p></td>
                                        <td class="z4-ve-republish-cell" data-label="Republishing approval">
                                            @if(!$placement->is_current_admin_removal)
                                                <span class="z4-exhibitions-status">{{ __('exhibitions::ui.auto_77a5cf142c') }}</span>
                                            @elseif($placement->republish_request_state === 'pending')
                                                <span class="z4-exhibitions-status is-pending">{{ __('exhibitions::ui.auto_c3a46242a8') }}</span>
                                                <p>{{ $placement->republish_artist_message }}</p>
                                            @elseif($placement->republish_request_state === 'approved')
                                                <span class="z4-exhibitions-status is-ready">{{ __('exhibitions::ui.auto_6f8063417a') }}</span>
                                                <p>{{ $placement->republish_decision_reason }}</p>
                                                <a href="{{ route('exhibitions.dashboard.virtual.index', ['tab' => 'available']) }}">{{ __('exhibitions::ui.auto_f5242fd61e') }}</a>
                                            @elseif($placement->republish_request_state === 'used')
                                                <span class="z4-exhibitions-status is-ready">{{ __('exhibitions::ui.auto_72e73ee9ef') }}</span>
                                            @else
                                                @if($placement->republish_request_state === 'rejected')
                                                    <span class="z4-exhibitions-status is-blocked">{{ __('exhibitions::ui.auto_17c6bfb514') }}</span>
                                                    <p>{{ $placement->republish_decision_reason }}</p>
                                                @endif
                                                <form class="z4-ve-republish-form" method="post" action="{{ route('exhibitions.dashboard.virtual.republish-request', $placement->public_id) }}">
                                                    @csrf
                                                    <label>
                                                        Why should this artwork be republished?
                                                        <textarea name="message" required minlength="10" maxlength="1000" rows="3"></textarea>
                                                    </label>
                                                    <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_1f871b8930') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">{{ __('exhibitions::ui.auto_7bc8fd3d31') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @else
                <section class="z4-exhibitions-panel">
                    <div class="z4-exhibitions-section-title">
                        <span>02</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_0fe5025af5') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_88e6f2b3f2') }}</p>
                        </div>
                    </div>
                    <form class="z4-ve-published-filters" method="get" action="{{ route('exhibitions.dashboard.virtual.index') }}">
                        <input type="hidden" name="tab" value="published">
                        <label class="is-search">
                            <span>{{ __('exhibitions::ui.auto_13348442cc') }}</span>
                            <input type="search" name="q" value="{{ $publishedFilters['q'] }}" placeholder="{{ __('exhibitions::ui.auto_5b7da1b76d') }}" maxlength="120">
                        </label>
                        <label>
                            <span>{{ __('exhibitions::ui.auto_75002fe484') }}</span>
                            <select name="hall">
                                <option value="">{{ __('exhibitions::ui.auto_df7f81e686') }}</option>
                                @foreach($publishedHallOptions as $hallOption)
                                    <option value="{{ $hallOption['id'] }}" @selected($publishedFilters['hall'] === $hallOption['id'])>{{ $hallOption['title'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}</span>
                            <select name="status">
                                <option value="">{{ __('exhibitions::ui.auto_96e3d39826') }}</option>
                                <option value="published" @selected($publishedFilters['status'] === 'published')>{{ __('exhibitions::ui.auto_9b9d8a976b') }}</option>
                                <option value="sold" @selected($publishedFilters['status'] === 'sold')>{{ __('exhibitions::ui.auto_db99d16b2d') }}</option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __('exhibitions::ui.auto_6b46ae4842') }}</span>
                            <select name="sort">
                                <option value="newest" @selected($publishedFilters['sort'] === 'newest')>{{ __('exhibitions::ui.auto_54afc9a7ad') }}</option>
                                <option value="oldest" @selected($publishedFilters['sort'] === 'oldest')>{{ __('exhibitions::ui.auto_c2d80ee4df') }}</option>
                                <option value="artwork" @selected($publishedFilters['sort'] === 'artwork')>{{ __('exhibitions::ui.auto_b4f4fa1814') }}</option>
                                <option value="hall" @selected($publishedFilters['sort'] === 'hall')>{{ __('exhibitions::ui.auto_f8944febe6') }}</option>
                            </select>
                        </label>
                        <button type="submit">{{ __('exhibitions::ui.auto_79ee641715') }}</button>
                    </form>
                    <div class="z4-ve-filter-summary" role="status">
                        <span>{{ __('exhibitions::ui.auto_b4e6101378') }} <strong>{{ $publishedPlacements->count() }}</strong> of <strong>{{ $publishedPlacementTotal }}</strong> {{ __('exhibitions::ui.auto_e955a3245d') }}</span>
                        @if($publishedFilters['q'] !== '' || $publishedFilters['hall'] !== null || $publishedFilters['status'] !== '' || $publishedFilters['sort'] !== 'newest')
                            <a href="{{ route('exhibitions.dashboard.virtual.index', ['tab' => 'published']) }}">{{ __('exhibitions::ui.auto_0b275442d6') }}</a>
                        @endif
                    </div>
                    <div class="z4-table-scroll">
                        <table class="z4-exhibitions-table z4-ve-artist-table is-published">
                            <thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_a8f14fa92f') }}</th><th>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}</th><th>{{ __('exhibitions::ui.auto_9b9d8a976b') }}</th><th>{{ __('exhibitions::ui.auto_004bf6c9a4') }}</th></tr></thead>
                            <tbody>
                                @forelse($publishedPlacements as $placement)
                                    <tr>
                                        <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $placement->artwork_title }}</strong></td>
                                        <td data-label="{{ __('exhibitions::ui.data_exhibition_display') }}">{{ $placement->environment_title }}<br><small>{{ $placement->room_title }} · {{ $placement->slot_name }}</small></td>
                                        <td data-label="{{ __('exhibitions::ui.data_status') }}">
                                            @if(!$placement->placement_dimensions_valid)
                                                <span class="z4-exhibitions-status is-blocked">{{ __('exhibitions::ui.auto_793990e4bf') }}</span>
                                                <small>{{ $placement->placement_dimensions_message }}</small>
                                            @elseif($placement->is_sold)
                                                <span class="z4-exhibitions-status">{{ __('exhibitions::ui.auto_db99d16b2d') }}</span>
                                            @else
                                                <span class="z4-exhibitions-status is-ready">{{ __('exhibitions::ui.auto_9b9d8a976b') }}</span>
                                            @endif
                                        </td>
                                        <td data-label="{{ __('exhibitions::ui.data_published') }}">
                                            <time datetime="{{ \Illuminate\Support\Carbon::parse($placement->placed_at)->toAtomString() }}">
                                                {{ \Illuminate\Support\Carbon::parse($placement->placed_at)->format('Y-m-d H:i') }}
                                            </time>
                                        </td>
                                        <td data-label="{{ __('exhibitions::ui.data_action') }}">
                                            @if(!$placement->is_sold)
                                                <form method="post" action="{{ route('exhibitions.dashboard.virtual.withdraw', $placement->public_id) }}" onsubmit="return confirm('Remove this artwork from the virtual exhibition?');">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Removed by the artist from their Virtual Exhibition dashboard.">
                                                    <button class="z4-exhibitions-button is-danger" type="submit">{{ __('exhibitions::ui.auto_e01a6756f6') }}</button>
                                                </form>
                                            @else
                                                <span>{{ __('exhibitions::ui.auto_22b31b25c5') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">{{ $publishedPlacementTotal > 0 ? __('exhibitions::ui.no_published_placements_match') : __('exhibitions::ui.no_published_artwork') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </main>
</x-frontend-layout>
