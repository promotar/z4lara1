@push('styles')
    <link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
    <main class="z4-exhibitions-admin" data-ve-placement-requests>
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_3abf194d6c') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_699e3116e2') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_816e60cef4') }}</p>
                </div>
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            <section class="z4-exhibitions-panel">
                <div class="z4-exhibitions-section-title">
                    <span>01</span>
                    <div>
                        <h2>{{ __('exhibitions::ui.auto_167fd05d9d') }}</h2>
                        <p>{{ __('exhibitions::ui.auto_9e9aa26abe') }}</p>
                    </div>
                </div>
                <div class="z4-table-scroll">
                    <table class="z4-exhibitions-table">
                        <thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_4047c5607d') }}</th><th>{{ __('exhibitions::ui.auto_2aaf7dedf4') }}</th><th>{{ __('exhibitions::ui.auto_71e7ef59b6') }}</th><th>{{ __('exhibitions::ui.auto_536e51d6a0') }}</th></tr></thead>
                        <tbody>
                            @forelse($republishRequests as $republishRequest)
                                <tr>
                                    <td><strong>{{ $republishRequest->artwork_title }}</strong></td>
                                    <td>{{ $republishRequest->artistic_name ?: $republishRequest->legal_name }}</td>
                                    <td>{{ $republishRequest->environment_title }}<br><small>{{ $republishRequest->room_title }} · {{ $republishRequest->slot_name }}</small></td>
                                    <td><p class="z4-ve-removal-reason">{{ $republishRequest->artist_message }}</p></td>
                                    <td class="z4-ve-republish-cell">
                                        <span @class(['z4-exhibitions-status', 'is-pending' => $republishRequest->state === 'pending', 'is-ready' => in_array($republishRequest->state, ['approved', 'used'], true), 'is-blocked' => $republishRequest->state === 'rejected'])>
                                            {{ ucfirst($republishRequest->state) }}
                                        </span>
                                        @if($republishRequest->state === 'pending')
                                            <form class="z4-ve-admin-review" method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.republish-requests.decision', $republishRequest->public_id) }}">
                                                @csrf
                                                <select name="decision" required>
                                                    <option value="approved">{{ __('exhibitions::ui.auto_6f7351657f') }}</option>
                                                    <option value="rejected">{{ __('exhibitions::ui.auto_d98ac12774') }}</option>
                                                </select>
                                                <input name="reason" required minlength="8" maxlength="500" placeholder="{{ __('exhibitions::ui.auto_e06c042fae') }}">
                                                <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_d0077072c5') }}</button>
                                            </form>
                                        @elseif($republishRequest->decision_reason)
                                            <p>{{ $republishRequest->decision_reason }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ __('exhibitions::ui.auto_6ef10954c6') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <form class="z4-ve-filter" method="get">
                <label>
                    {{ __('exhibitions::ui.auto_ec53a8c4f0') }}
                    <select name="status">
                        <option value="">{{ __('exhibitions::ui.auto_96e3d39826') }}</option>
                        @foreach(['submitted', 'changes_requested', 'approved', 'published', 'rejected', 'withdrawn', 'removed', 'expired'] as $state)
                            <option value="{{ $state }}" @selected($statusFilter === $state)>{{ __('exhibitions::ui.state_'.$state) }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit">{{ __('exhibitions::ui.auto_d7778d0c64') }}</button>
            </form>

            <section class="z4-exhibitions-panel">
                <div class="z4-exhibitions-section-title">
                    <span>02</span>
                    <div><h2>{{ __('exhibitions::ui.auto_bfb245b3e0') }}</h2><p>{{ __('exhibitions::ui.auto_1845c92dfb') }}</p></div>
                </div>
                <div class="z4-table-scroll">
                    <table class="z4-exhibitions-table z4-ve-requests-table">
                        <thead>
                            <tr>
                                <th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th>
                                <th>{{ __('exhibitions::ui.auto_4047c5607d') }}</th>
                                <th>{{ __('exhibitions::ui.auto_47866563c5') }}</th>
                                <th>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}</th>
                                <th>{{ __('exhibitions::ui.auto_5c835c2242') }}</th>
                                <th><span class="z4-visually-hidden">{{ __('exhibitions::ui.auto_004bf6c9a4') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $request)
                                <tr>
                                    <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $request->artwork_title }}</strong></td>
                                    <td data-label="{{ __('exhibitions::ui.data_artist') }}">{{ $request->artistic_name ?: $request->legal_name }}</td>
                                    <td data-label="{{ __('exhibitions::ui.data_room_slot') }}">
                                        {{ $request->room_title }}
                                        <small>{{ $request->slot_code }} · {{ $request->slot_name }}</small>
                                    </td>
                                    <td data-label="{{ __('exhibitions::ui.data_status') }}"><span class="z4-exhibitions-status">{{ __('exhibitions::ui.state_'.$request->status) }}</span></td>
                                    <td data-label="Submitted">
                                        @if($request->submitted_at)
                                            @php($submittedAt = \Illuminate\Support\Carbon::parse($request->submitted_at))
                                            <time datetime="{{ $submittedAt->toAtomString() }}">
                                                <span>{{ $submittedAt->format('Y-m-d') }}</span>
                                                <small>{{ $submittedAt->format('H:i:s') }}</small>
                                            </time>
                                        @else
                                            <span>{{ __('exhibitions::ui.auto_f03ab16cd5') }}</span>
                                        @endif
                                    </td>
                                    <td class="z4-ve-request-action" data-label="{{ __('exhibitions::ui.data_action') }}">
                                        <a href="{{ route('admin.plugins.exhibitions.virtual.requests.review', $request->public_id) }}">{{ __('exhibitions::ui.auto_d373afbab8') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="z4-exhibitions-empty" colspan="6">{{ __('exhibitions::ui.auto_cfaaee3224') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <x-platform-pagination :paginator="$requests" />
        </div>
    </main>
</x-app-layout>
