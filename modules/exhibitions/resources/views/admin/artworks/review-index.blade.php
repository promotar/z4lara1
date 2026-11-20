@php
    $reviewFilters = [
        'not_opened' => __('exhibitions::ui.review_state_not_opened'),
        'under_review' => __('exhibitions::ui.review_state_under_review'),
        'changes_requested' => __('exhibitions::ui.review_state_changes_requested'),
        'documents_requested' => __('exhibitions::ui.review_state_documents_requested'),
        'escalated' => __('exhibitions::ui.review_state_escalated'),
    ];
@endphp

@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
<main class="z4-exhibitions-admin" data-artwork-review-queue>
    <div class="z4-exhibitions-container">
        <header class="z4-exhibitions-heading z4-review-queue-heading">
            <div>
                <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_cbfde6aca1') }}</span>
                <h1>{{ __('exhibitions::ui.auto_f4eaa2fb6e') }}</h1>
                <p>{{ __('exhibitions::ui.auto_7c86dad400') }}</p>
            </div>
        </header>

        <nav class="z4-review-queue-filters" aria-label="{{ __('exhibitions::ui.auto_781e38182f') }}">
            <a class="{{ $state === '' ? 'is-active' : '' }}" href="{{ route('admin.plugins.exhibitions.artworks.review.index') }}" @if($state === '') aria-current="page" @endif>
                <span>{{ __('exhibitions::ui.auto_b1c94ca2fb') }}</span>
            </a>
            @foreach($reviewFilters as $filter => $label)
                <a class="{{ $state === $filter ? 'is-active' : '' }}" href="{{ route('admin.plugins.exhibitions.artworks.review.index', ['state' => $filter]) }}" @if($state === $filter) aria-current="page" @endif>
                    <span>{{ $label }}</span>
                    <b>{{ $counts[$filter] ?? 0 }}</b>
                </a>
            @endforeach
        </nav>

        <section class="z4-exhibitions-panel z4-review-queue-panel">
            <div class="z4-exhibitions-table-wrap">
                <table class="z4-exhibitions-table z4-review-queue-table">
                    <thead>
                        <tr>
                            <th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th>
                            <th>{{ __('exhibitions::ui.auto_38e97dca59') }}</th>
                            <th>{{ __('exhibitions::ui.auto_4ce9d04419') }}</th>
                            <th>{{ __('exhibitions::ui.auto_b7f41fc141') }}</th>
                            <th><span class="z4-visually-hidden">{{ __('exhibitions::ui.auto_004bf6c9a4') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php($reviewState = $row->review_state ?? 'not_opened')
                            <tr>
                                <td data-label="{{ __('exhibitions::ui.data_artwork') }}"><strong>{{ $row->title ?: 'Untitled artwork' }}</strong></td>
                                <td data-label="{{ __('exhibitions::ui.data_artwork_state') }}"><span class="z4-review-state is-{{ str_replace('_', '-', $row->artwork_state) }}">{{ __('exhibitions::ui.state_'.$row->artwork_state) }}</span></td>
                                <td data-label="{{ __('exhibitions::ui.data_review_status') }}"><span class="z4-review-state is-{{ str_replace('_', '-', $reviewState) }}">{{ __('exhibitions::ui.state_'.$reviewState) }}</span></td>
                                <td data-label="{{ __('exhibitions::ui.data_round') }}"><span class="z4-review-round">{{ $row->round_number ?? '—' }}</span></td>
                                <td data-label="{{ __('exhibitions::ui.data_action') }}" class="z4-review-queue-action-cell"><a class="z4-exhibitions-button is-secondary z4-review-queue-action" href="{{ route('admin.plugins.exhibitions.artworks.review.show', $row->public_id) }}">{{ __('exhibitions::ui.auto_d373afbab8') }}</a></td>
                            </tr>
                        @empty
                            <tr><td class="z4-exhibitions-empty" colspan="5">{{ __('exhibitions::ui.auto_1b4265e2df') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-platform-pagination :paginator="$rows" />
        </section>
    </div>
</main>
</x-app-layout>
