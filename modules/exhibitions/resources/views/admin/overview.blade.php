@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-dashboard-2" data-exhibitions-stylesheet>@endpush
<x-app-layout>
<main class="z4-exhibitions-admin" data-admin-dashboard data-exhibitions-foundation>
<div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading">
        <div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_f2e8aff19d') }}</span><h1>{{ __('exhibitions::ui.auto_741540f0f2') }}</h1><p>{{ __('exhibitions::ui.auto_e9d6e4c322') }}</p></div>
        <span class="z4-exhibitions-status {{ $readiness->available ? 'is-ready' : 'is-blocked' }}">{{ $readiness->available ? __('exhibitions::ui.readiness_ready') : __('exhibitions::ui.readiness_blocked') }}</span>
    </header>

    <section class="z4-admin-metric-grid" aria-label="{{ __('exhibitions::ui.auto_dce0a00436') }}">
        @forelse($metrics as $metric)
            <a class="z4-admin-metric" href="{{ route($metric['route'], $metric['params']) }}"><small>{{ $metric['label'] }}</small><strong>{{ number_format($metric['value']) }}</strong><span>{{ __('exhibitions::ui.auto_352b0b0aa0') }}</span></a>
        @empty
            <article class="z4-exhibitions-panel"><strong>{{ __('exhibitions::ui.auto_8a631fbe11') }}</strong><p>{{ __('exhibitions::ui.auto_578fbe0901') }}</p></article>
        @endforelse
    </section>

    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-panel-heading"><div><span>01</span><h2>{{ __('exhibitions::ui.auto_dab2e15674') }}</h2></div></div>
        <nav class="z4-admin-workspaces" aria-label="{{ __('exhibitions::ui.auto_be31668faa') }}">
            @can('exhibitions.review')<a href="{{ route('admin.plugins.exhibitions.applications.index') }}"><strong>{{ __('exhibitions::ui.auto_86f468d80b') }}</strong><span>{{ __('exhibitions::ui.auto_2356881d99') }}</span></a>@endcan
            @can('exhibitions.review')<a href="{{ route('admin.plugins.exhibitions.artworks.review.index') }}"><strong>{{ __('exhibitions::ui.auto_f6224c6e8d') }}</strong><span>{{ __('exhibitions::ui.auto_09e130bca0') }}</span></a>@endcan
            @can('exhibitions.finance')<a href="{{ route('admin.plugins.exhibitions.sales.index') }}"><strong>{{ __('exhibitions::ui.auto_514c9c452e') }}</strong><span>{{ __('exhibitions::ui.auto_70e6764fbf') }}</span></a>@endcan
            @can('exhibitions.finance')<a href="{{ route('admin.plugins.exhibitions.settlements.index') }}"><strong>{{ __('exhibitions::ui.auto_bd48ea4cd6') }}</strong><span>{{ __('exhibitions::ui.auto_7e8ef0a9e7') }}</span></a>@endcan
            @can('exhibitions.support')<a href="{{ route('admin.plugins.exhibitions.disputes.index') }}"><strong>{{ __('exhibitions::ui.auto_a9613f950a') }}</strong><span>{{ __('exhibitions::ui.auto_3bb59c080f') }}</span></a>@endcan
            @can('exhibitions.manage_settings')<a href="{{ route('admin.plugins.exhibitions.settings') }}"><strong>{{ __('exhibitions::ui.auto_858e8c9978') }}</strong><span>{{ __('exhibitions::ui.auto_90c3d27f61') }}</span></a>@endcan
        </nav>
    </section>

</div>
</main>
</x-app-layout>
