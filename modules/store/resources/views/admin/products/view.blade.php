<x-app-layout>
@php($displayStatus = $record->status === 'archived' ? 'archived' : ($record->status === 'draft' ? 'draft' : ($record->visibility === 'hidden' ? 'hidden' : 'published')))
<main class="z4-store-admin z4-store-form-page" data-z4-store-foundation>
    <div class="z4-store-container z4-store-container-narrow">
        @include('store::admin._navigation')
        @if(session('status'))<div class="z4-store-alert is-success">{{ session('status') }}</div>@endif
        @if(isset($errors) && $errors->any())<div class="z4-store-alert is-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <header class="z4-store-heading">
            <div><a class="z4-store-back" href="{{ route('admin.plugins.store.products.index') }}">{{ __('store::ui.auto_13a16e208e') }}</a><div class="z4-store-title-line"><h1>{{ $record->name }}</h1><span class="z4-store-status is-{{ $displayStatus }}">{{ $displayStatus === 'archived' ? 'Deleted' : ucfirst($displayStatus) }}</span></div><p>{{ __('store::ui.auto_669c86b79b') }}</p></div>
            @if($record->type_code !== 'artwork')
                <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.products.edit', $record->public_id) }}">{{ __('store::ui.auto_7177e0adfa') }}</a>
            @elseif(in_array($record->artwork_workflow_state, ['draft', 'changes_required', 'rejected'], true))
                @can('store.products.update')
                    <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.artworks.edit', $record->public_id) }}">{{ __('store::ui.auto_7223d621a6') }}</a>
                @endcan
            @elseif(in_array($record->artwork_workflow_state, ['submitted', 'under_review'], true))
                @can('store.artworks.review')
                    <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.artworks.review.show', $record->public_id) }}">{{ __('store::ui.auto_75f5c51471') }}</a>
                @endcan
            @elseif($record->artwork_workflow_state === 'approved' && $record->status === 'published')
                @can('store.products.update')
                    @can('store.products.publish')
                        <form method="post" action="{{ route('admin.plugins.store.artworks.unpublish-for-edit', $record->public_id) }}" data-z4-confirm data-confirm-title="{{ __('store::ui.auto_fe7c50970f') }}" data-confirm-message="Store will temporarily hide the artwork while you edit it. Your completed changes can be published directly without a review step." data-confirm-label="Edit artwork">
                            @csrf
                            <input type="hidden" name="confirmation" value="unpublish-for-edit">
                            <button class="z4-store-btn z4-store-btn-primary" type="submit">{{ __('store::ui.auto_7223d621a6') }}</button>
                        </form>
                    @endcan
                @endcan
            @endif
        </header>

        @if($media->isNotEmpty())
        <section class="z4-store-preview-gallery">
            @foreach($media as $item)@if($item->path)<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($item->path) }}" alt="{{ $item->original_filename }}" @class(['is-primary' => $item->is_primary])>@endif @endforeach
        </section>
        @endif

        <div class="z4-store-preview-grid">
            <section class="z4-store-form-card"><span class="z4-store-eyebrow">{{ __('store::ui.auto_c52feaf85b') }}</span><h2>{{ $record->name }}</h2><p class="z4-store-lead">{{ $record->short_description ?: 'No short description.' }}</p><div class="z4-store-richtext">{!! nl2br(e($record->description ?: 'No full description.')) !!}</div></section>
            <aside class="z4-store-side-card"><h2>{{ __('store::ui.auto_59e33184c9') }}</h2><dl><div><dt>SKU</dt><dd>{{ $variants->firstWhere('is_default', true)?->sku ?: '—' }}</dd></div><div><dt>{{ __('store::ui.auto_1be6f9eb56') }}</dt><dd>{{ $record->brand_name ?: '—' }}</dd></div><div><dt>{{ __('store::ui.auto_af1b98adf7') }}</dt><dd>{{ $categories->implode(', ') ?: 'Uncategorized' }}</dd></div><div><dt>{{ __('store::ui.auto_1729a56cfc') }}</dt><dd>{{ $displayStatus === 'archived' ? 'Deleted' : ucfirst($displayStatus) }}</dd></div><div><dt>{{ __('store::ui.auto_15422d54ec') }}</dt><dd>{{ $record->is_featured ? 'Yes' : 'No' }}</dd></div></dl></aside>
        </div>

        <section class="z4-store-form-card"><header class="z4-store-section-heading"><span>¤</span><div><h2>{{ __('store::ui.auto_89256ba4fd') }}</h2><p>{{ __('store::ui.auto_d53b542b3c') }}</p></div></header><div class="z4-store-variant-summary">@forelse($variants as $variant)<article><div><strong>{{ $variant->sku ?: $variant->public_id }}</strong><small>{{ $variant->status === 'archived' ? 'Deleted' : ucfirst($variant->status) }}</small></div><div><strong>{{ $variant->price ? app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $variant->price->amount_minor) : 'Price not set' }}</strong><small>{{ $variant->price?->type ? ucfirst($variant->price->type).' price' : '' }}</small></div><div><strong>{{ $variant->inventory ? ((int)$variant->inventory->on_hand - (int)$variant->inventory->reserved) : '—' }}</strong><small>{{ __('store::ui.auto_e4894ca167') }}</small></div></article>@empty<div class="z4-store-inline-empty">{{ __('store::ui.auto_bc2ac60956') }}</div>@endforelse</div></section>
    </div>
</main>
</x-app-layout>
