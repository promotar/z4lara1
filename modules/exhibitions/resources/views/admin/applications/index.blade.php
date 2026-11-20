@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-application-queue-2" data-exhibitions-stylesheet>@endpush
<x-app-layout>
    <main class="z4-exhibitions-admin" data-exhibitions-admin-queue>
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_718e1b9b49') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_88cd7f2fb6') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_9cfab002f9') }}</p>
                </div>
                <span class="z4-exhibitions-status">{{ __('exhibitions::ui.auto_516c163722') }}</span>
            </header>

            <div class="z4-exhibitions-counts">
                @foreach($states as $state)
                    <a href="{{ route('admin.plugins.exhibitions.applications.index', ['state' => $state->value]) }}">
                        <strong>{{ $counts[$state->value] ?? 0 }}</strong>
                        <span>{{ $state->label() }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" class="z4-exhibitions-filters">
                <label>
                    <span>{{ __('exhibitions::ui.auto_13348442cc') }}</span>
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('exhibitions::ui.auto_ad6768d71d') }}">
                </label>
                <label>
                    <span>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}</span>
                    <select name="state">
                        <option value="">{{ __('exhibitions::ui.auto_96e3d39826') }}</option>
                        @foreach($states as $state)
                            <option value="{{ $state->value }}" @selected(($filters['state'] ?? '') === $state->value)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit">{{ __('exhibitions::ui.auto_79ee641715') }}</button>
            </form>

            <section class="z4-exhibitions-panel z4-exhibitions-table-wrap">
                <table class="z4-exhibitions-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('exhibitions::ui.auto_bec632bf40') }}</th>
                            <th scope="col">{{ __('exhibitions::ui.auto_bb7cf40f9d') }}</th>
                            <th scope="col">{{ __('exhibitions::ui.auto_e498749f3c') }}</th>
                            <th scope="col">{{ __('exhibitions::ui.auto_6ce0096786') }}</th>
                            <th scope="col">{{ __('exhibitions::ui.auto_ff0a3b7f3d') }}</th>
                            <th scope="col"><span class="z4-visually-hidden">{{ __('exhibitions::ui.auto_004bf6c9a4') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $application)
                            <tr>
                                <td data-label="{{ __('exhibitions::ui.data_applicant') }}">
                                    <strong>{{ $application->applicant_name }}</strong>
                                    <small>{{ $application->applicant_account_email }}</small>
                                </td>
                                <td data-label="{{ __('exhibitions::ui.data_artistic_name') }}">{{ $application->artistic_name ?: '—' }}</td>
                                <td data-label="{{ __('exhibitions::ui.data_application') }}">
                                    <span class="z4-exhibitions-pill">{{ \Modules\Exhibition\Domain\Applications\ApplicationState::from($application->state)->label() }}</span>
                                </td>
                                <td data-label="{{ __('exhibitions::ui.data_artist_account') }}">
                                    @if(\Modules\Exhibition\Domain\Artists\ArtistAccountState::tryFrom((string) $application->artist_account_state))
                                        <span class="z4-exhibitions-pill">{{ \Modules\Exhibition\Domain\Artists\ArtistAccountState::from($application->artist_account_state)->label() }}</span>
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('exhibitions::ui.data_updated') }}"><time>{{ $application->updated_at }}</time></td>
                                <td data-label="{{ __('exhibitions::ui.data_action') }}" class="z4-application-row-action">
                                    <a href="{{ route('admin.plugins.exhibitions.applications.show', $application->public_id) }}">
                                        {{ \Modules\Exhibition\Domain\Artists\ArtistAccountState::tryFrom((string) $application->artist_account_state) ? __('exhibitions::ui.manage') : __('exhibitions::ui.review') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="z4-application-empty-row">
                                <td colspan="6" class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_43bc57f9b8') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <x-platform-pagination :paginator="$applications" />
            </section>
        </div>
    </main>
</x-app-layout>
