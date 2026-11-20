@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artist-financial-terms-1">
@endpush

<x-app-layout>
<main class="z4-exhibitions-admin">
<div class="z4-exhibitions-container z4-financial-terms-page">
    @php
        $artistName = $artist->artistic_name ?: ($artist->legal_name ?: ($artist->user_name ?: $artist->contact_email));
        $currentSource = $effectiveTerm?->source ?? 'platform_default';
        $currentRate = $currentSource === 'custom' && $effectiveTerm?->commission_basis_points !== null
            ? number_format($effectiveTerm->commission_basis_points / 100, 3, '.', '')
            : $defaultRate;
    @endphp

    <header class="z4-exhibitions-heading">
        <div>
            <a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.workspace', ['area' => 'artists', 'tab' => 'active']) }}">{{ __('exhibitions::ui.auto_45460a8fb9') }}</a>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_408b7070e0') }}</span>
            <h1>{{ $artistName }}</h1>
            <p>{{ __('exhibitions::ui.auto_4fe82b94d2') }}</p>
        </div>
        <span class="z4-exhibitions-pill">{{ Illuminate\Support\Str::headline($artist->state) }}</span>
    </header>

    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="z4-financial-terms-summary" aria-label="{{ __('exhibitions::ui.auto_e7819dc8f0') }}">
        <article><span>{{ __('exhibitions::ui.auto_65d0858c46') }}</span><strong>{{ $currentSource === 'custom' ? 'Custom contractual rate' : 'Platform default' }}</strong></article>
        <article><span>{{ __('exhibitions::ui.auto_8dd2e14b27') }}</span><strong>{{ $currentRate }}%</strong></article>
        <article><span>{{ __('exhibitions::ui.auto_82169c325d') }}</span><strong>{{ number_format(100 - (float) $currentRate, 3) }}%</strong></article>
        <article><span>{{ __('exhibitions::ui.auto_cf08b7acdd') }}</span><strong>{{ $effectiveTerm?->effective_from ? Illuminate\Support\Carbon::parse($effectiveTerm->effective_from)->format('d M Y H:i') : 'Uses current platform setting' }}</strong></article>
    </section>

    @if($scheduledTerm)
        <aside class="z4-exhibitions-notice" role="status">
            <strong>{{ __('exhibitions::ui.auto_d93513b2b2') }}</strong>
            <p>{{ __('exhibitions::ui.auto_c623ac622a') }}</p>
        </aside>
    @endif

    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-panel-heading"><div><span>01</span><h2>{{ __('exhibitions::ui.auto_2bdb0c85ae') }}</h2></div><p>{{ __('exhibitions::ui.auto_922584d33f') }}</p></div>
        <form method="post" action="{{ route('admin.plugins.exhibitions.artists.financial-terms.store', $artist->application_public_id) }}" class="z4-financial-terms-form">
            @csrf
            <fieldset>
                <legend>{{ __('exhibitions::ui.auto_65d0858c46') }}</legend>
                <label class="z4-financial-source-option"><input type="radio" name="commission_source" value="platform_default" @checked(old('commission_source', $currentSource) === 'platform_default')><span><strong>{{ __('exhibitions::ui.auto_50f9f82a14') }}</strong><small>{{ __('exhibitions::ui.auto_db0ff09c55') }}</small></span></label>
                <label class="z4-financial-source-option"><input type="radio" name="commission_source" value="custom" @checked(old('commission_source', $currentSource) === 'custom')><span><strong>{{ __('exhibitions::ui.auto_038d1ca6b5') }}</strong><small>{{ __('exhibitions::ui.auto_2a38967cc7') }}</small></span></label>
            </fieldset>
            <label><span>{{ __('exhibitions::ui.auto_4fa2c965f7') }}</span><input type="number" name="custom_commission" min="0" max="100" step="0.001" value="{{ old('custom_commission', $currentSource === 'custom' ? $currentRate : '') }}" placeholder="15.000"><small>{{ __('exhibitions::ui.auto_203fa3fa65') }}</small></label>
            <label><span>{{ __('exhibitions::ui.auto_2a4defecfb') }}</span><input type="datetime-local" name="effective_from" value="{{ old('effective_from', now()->format('Y-m-d\TH:i')) }}" required></label>
            <label class="is-wide"><span>{{ __('exhibitions::ui.auto_fffcb4d1e1') }}</span><textarea name="reason" rows="4" minlength="8" maxlength="1000" required placeholder="Artist contract agreement">{{ old('reason') }}</textarea><small>{{ __('exhibitions::ui.auto_0e8d35cf3c') }}</small></label>
            <div class="z4-financial-terms-guard is-wide"><strong>{{ __('exhibitions::ui.auto_236ad7f565') }}</strong><span>{{ __('exhibitions::ui.auto_8bef725049') }}</span></div>
            <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_91c971c82a') }}</button>
        </form>
    </section>

    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-panel-heading"><div><span>02</span><h2>{{ __('exhibitions::ui.auto_6a3eb69620') }}</h2></div><p>{{ __('exhibitions::ui.auto_75332be2b3') }}</p></div>
        <div class="z4-exhibitions-table-wrap"><table class="z4-exhibitions-table"><thead><tr><th>{{ __('exhibitions::ui.auto_f31bbdd1b3') }}</th><th>{{ __('exhibitions::ui.auto_8dd2e14b27') }}</th><th>{{ __('exhibitions::ui.auto_2a4defecfb') }}</th><th>{{ __('exhibitions::ui.auto_fffcb4d1e1') }}</th><th>{{ __('exhibitions::ui.auto_2f8c27c209') }}</th></tr></thead><tbody>
        @forelse($history as $term)<tr>
            <td>{{ $term->source === 'custom' ? 'Custom commission' : 'Platform default' }}</td>
            <td>{{ $term->commission_basis_points === null ? 'Derived from platform setting' : number_format($term->commission_basis_points / 100, 3).'%' }}</td>
            <td>{{ Illuminate\Support\Carbon::parse($term->effective_from)->format('Y-m-d H:i') }}</td>
            <td>{{ $term->reason }}</td>
            <td>{{ $term->actor_name ?: 'System migration' }}</td>
        </tr>@empty<tr><td colspan="5" class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_dcbc71f28e') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
</main>
</x-app-layout>
