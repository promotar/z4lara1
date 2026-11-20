@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}">
@endpush
<x-app-layout>
<main class="z4-exhibitions-admin"><div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading"><div>
        <a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.artworks.publication.index') }}">&larr; {{ __('exhibitions::ui.publication_queue') }}</a>
        <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_1489fa77de') }}</span>
        <h1>{{ $artwork->title }}</h1>
        <p>Approval data remains authoritative in Exhibitions; Store owns cart, checkout, and orders.</p>
    </div><span class="z4-exhibitions-status">{{ ucwords(str_replace('_', ' ', $publication->state ?? 'not published')) }}</span></header>

    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($pricing)
        <div class="z4-exhibitions-alert is-success">
            <strong>{{ __('exhibitions::ui.auto_2dee2ce6f8') }}</strong><br>
            Frame and packaging are included in this approved price. Shipping and tax are calculated by Store at checkout; they are not added here.
        </div>
    @endif

    <section class="z4-exhibitions-grid">
        <article class="z4-exhibitions-panel"><h2>{{ __('exhibitions::ui.auto_11a7f954e5') }}</h2><dl class="z4-exhibitions-details">
            <div><dt>{{ __('exhibitions::ui.auto_deb1051765') }}</dt><dd>{{ $publication->store_product_public_id ?? 'Not linked' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_492f18b608') }}</dt><dd>{{ $publication->store_variant_public_id ?? 'Not linked' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_4d604dd8f0') }}</dt><dd>{{ $publication->store_inventory_public_id ?? 'Not linked' }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_7ba3b8236d') }}</dt><dd>{{ $publication->attempt_count ?? 0 }}</dd></div>
            <div><dt>{{ __('exhibitions::ui.auto_e139a58551') }}</dt><dd>{{ $publication->failure_code ?? 'None' }} {{ $publication->failure_message ?? '' }}</dd></div>
        </dl></article>
        <article class="z4-exhibitions-panel"><h2>{{ __('exhibitions::ui.auto_2427f22b58') }}</h2>
            @foreach(['publish' => 'Publish / safe retry', 'reconcile' => 'Reconcile Store link', 'withdraw' => 'Withdraw from sale'] as $action => $label)
                <form method="post" action="{{ route('admin.plugins.exhibitions.artworks.publication.'.$action, $artwork->public_id) }}" class="z4-exhibitions-inline-form">
                    @csrf<input name="reason" placeholder="{{ __('exhibitions::ui.auto_ab386312d5') }}" required>
                    <button class="{{ $action === 'withdraw' ? 'is-danger' : 'is-primary' }}">{{ $label }}</button>
                </form>
            @endforeach
        </article>
    </section>
    <section class="z4-exhibitions-panel"><h2>{{ __('exhibitions::ui.auto_487740a184') }}</h2><ol class="z4-exhibitions-timeline">
        @forelse($events as $event)<li><span></span><div><strong>{{ ucfirst($event->action) }} &middot; {{ __('exhibitions::ui.state_'.$event->state) }}</strong><p>{{ $event->reason }}</p><time>{{ $event->occurred_at }}</time></div></li>
        @empty<li>{{ __('exhibitions::ui.auto_c7d1e4d8ac') }}</li>@endforelse
    </ol></section>
</div></main>
</x-app-layout>
