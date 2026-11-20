@push('styles')
<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}">
@endpush
@php($displayState = $fulfillment->state === 'artist_notification_sent' ? ($fulfillment->sale_committed ? 'sale_confirmed' : 'store_reserved') : $fulfillment->state)
<x-app-layout>
<main class="z4-exhibitions-admin">
<div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading">
        <div>
            <a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.fulfillment.index') }}">{{ __('exhibitions::ui.auto_624f952180') }}</a>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_5e6308f8e0') }}</span>
            <h1>{{ $fulfillment->title }}</h1>
            <p>{{ __('exhibitions::ui.auto_d58f8d85a7') }}</p>
        </div>
        <span class="z4-exhibitions-status">{{ __('exhibitions::ui.state_'.$displayState) }}</span>
    </header>

    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error">{{ implode(' · ', $errors->all()) }}</div>@endif
    @unless($fulfillment->sale_committed)
        <div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_ba34084933') }}</strong><p>{{ __('exhibitions::ui.auto_7e9dff6c56') }}</p></div>
    @endunless

    <section class="z4-exhibitions-grid">
        <article class="z4-exhibitions-panel">
            <h2>{{ __('exhibitions::ui.auto_e00df490a4') }}</h2>
            <dl class="z4-exhibitions-details">
                <div><dt>{{ __('exhibitions::ui.auto_23a7783879') }}</dt><dd>{{ $fulfillment->preparation_deadline }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_96fd07f824') }}</dt><dd>{{ $fulfillment->pickup_scheduled_at ?: 'Not scheduled' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_2d82833085') }}</dt><dd>{{ ucfirst($fulfillment->custody_holder) }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_9e2941b3c8') }}</dt><dd>{{ $fulfillment->condition_summary ?: 'Pending inspection' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_eb0f48a107') }}</dt><dd>{{ $fulfillment->certificate_included ? 'Included' : 'Pending' }}</dd></div>
                <div><dt>{{ __('exhibitions::ui.auto_148e73e5d9') }}</dt><dd>{{ $fulfillment->carrier_reference ?: 'Pending' }}</dd></div>
            </dl>
            @if($fulfillment->sale_committed)
                <h3>{{ __('exhibitions::ui.auto_8acedaf21d') }}</h3>
                <form method="post" action="{{ route('admin.plugins.exhibitions.fulfillment.pickup', $fulfillment->public_id) }}" class="z4-exhibitions-inline-form">
                    @csrf
                    <input type="datetime-local" name="pickup_scheduled_at" required>
                    <input name="notes" required placeholder="{{ __('exhibitions::ui.auto_27b1542f73') }}">
                    <input type="hidden" name="idempotency_key" value="pickup:{{ $fulfillment->public_id }}:{{ $fulfillment->lock_version }}">
                    <button>{{ __('exhibitions::ui.auto_25f7347236') }}</button>
                </form>
            @endif
        </article>

        <article class="z4-exhibitions-panel">
            <h2>{{ __('exhibitions::ui.auto_e3374fed44') }}</h2>
            @if($allowedTransitions)
                <form method="post" action="{{ route('admin.plugins.exhibitions.fulfillment.transition', $fulfillment->public_id) }}" class="z4-exhibitions-form">
                    @csrf
                    <select name="state" required>@foreach($allowedTransitions as $state)<option value="{{ $state }}">{{ __('exhibitions::ui.state_'.$state) }}</option>@endforeach</select>
                    <textarea name="notes" required placeholder="Required operational notes"></textarea>
                    <input name="condition_summary" placeholder="{{ __('exhibitions::ui.auto_0f6d1e1435') }}">
                    <input name="carrier_reference" placeholder="{{ __('exhibitions::ui.auto_148e73e5d9') }}">
                    <label><input type="checkbox" name="certificate_included" value="1"> {{ __('exhibitions::ui.auto_4f7f413cad') }}</label>
                    <input type="hidden" name="idempotency_key" value="ops:{{ $fulfillment->public_id }}:{{ $fulfillment->lock_version }}">
                    <button>{{ __('exhibitions::ui.auto_0eec90f265') }}</button>
                </form>
            @elseif($fulfillment->sale_committed)
                <p>{{ __('exhibitions::ui.auto_65ef42e62c') }}</p>
            @else
                <p>{{ __('exhibitions::ui.auto_1288a6209e') }}</p>
            @endif
        </article>
    </section>

    <section class="z4-exhibitions-panel">
        <h2>{{ __('exhibitions::ui.auto_7bbf2ab582') }}</h2>
        @if($fulfillment->sale_committed)
            <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.exhibitions.fulfillment.evidence', $fulfillment->public_id) }}" class="z4-exhibitions-inline-form">
                @csrf
                <select name="role"><option value="collection_condition">{{ __('exhibitions::ui.auto_d15b505cce') }}</option><option value="inspection_condition">{{ __('exhibitions::ui.auto_b4faa7ed95') }}</option><option value="damage">{{ __('exhibitions::ui.auto_b9f24e8353') }}</option><option value="return">{{ __('exhibitions::ui.auto_988fd738de') }}</option></select>
                <input type="file" name="file" required>
                <button>{{ __('exhibitions::ui.auto_aed0cd3d59') }}</button>
            </form>
        @endif
        <p>{{ __('exhibitions::ui.auto_8c67f23c16') }}</p>
    </section>

    <section class="z4-exhibitions-panel">
        <h2>{{ __('exhibitions::ui.auto_1e3ef833cb') }}</h2>
        <ol class="z4-exhibitions-timeline">
            @foreach($events as $event)
                <li><span></span><div><strong>{{ __('exhibitions::ui.state_'.$event->to_state) }} · {{ $event->actor_role }}</strong><p>{{ $event->notes }}</p><time>{{ $event->occurred_at }}</time></div></li>
            @endforeach
        </ol>
    </section>
</div>
</main>
</x-app-layout>
