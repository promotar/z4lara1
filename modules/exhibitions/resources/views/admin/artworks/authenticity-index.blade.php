@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}" data-exhibitions-stylesheet>
@endpush
<x-app-layout>
<main class="z4-exhibitions-admin" data-admin-workspace>
<div class="z4-exhibitions-container">
<header class="z4-exhibitions-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_c3c24f6c59') }}</span><h1>{{ __('exhibitions::ui.auto_b7442413f8') }}</h1><p>{{ __('exhibitions::ui.auto_bd40e7348b') }}</p></div></header>
<section class="z4-exhibitions-panel">
<div class="z4-exhibitions-table-wrap"><table class="z4-exhibitions-table"><thead><tr><th>{{ __('exhibitions::ui.auto_f1eabc4e8f') }}</th><th>{{ __('exhibitions::ui.auto_38e97dca59') }}</th><th>{{ __('exhibitions::ui.auto_5b6cf86926') }}</th><th>{{ __('exhibitions::ui.auto_004bf6c9a4') }}</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td data-label="{{ __('exhibitions::ui.data_artwork') }}">{{ $row->title ?: 'Untitled' }}</td><td data-label="{{ __('exhibitions::ui.data_artwork_state') }}">{{ __('exhibitions::ui.state_'.$row->artwork_state) }}</td><td data-label="{{ __('exhibitions::ui.data_documentation') }}">{{ __('exhibitions::ui.state_'.($row->authenticity_state ?: 'not_submitted')) }}</td><td data-label="{{ __('exhibitions::ui.data_action') }}"><a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.artworks.authenticity.show',$row->public_id) }}">{{ __('exhibitions::ui.auto_4419e74409') }}</a></td></tr>
@empty<tr><td colspan="4"><div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_d7b8ef5606') }}</strong><p>{{ __('exhibitions::ui.auto_df40bae725') }}</p></div></td></tr>@endforelse
</tbody></table></div>
<x-platform-pagination :paginator="$rows" />
</section>
</div>
</main>
</x-app-layout>
