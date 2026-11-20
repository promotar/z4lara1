@push('styles')<link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}" data-exhibitions-stylesheet>@endpush
<x-frontend-layout><main class="z4-exhibitions-admin z4-artist-dashboard" data-artist-dashboard-sales><div class="z4-exhibitions-container">
@include('exhibitions::artist.dashboard._nav',['title' => __('exhibitions::ui.artist_page_sales_title'),'subtitle' => __('exhibitions::ui.artist_page_sales_subtitle')])
<section class="z4-exhibitions-panel"><div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_8145f48721') }}</strong><p>{{ __('exhibitions::ui.auto_6d5e3fa4fa') }}</p></div></section>
</div></main></x-frontend-layout>
