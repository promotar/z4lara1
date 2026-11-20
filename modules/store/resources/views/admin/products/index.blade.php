<x-app-layout>
@php
    $isArchivedView = request()->routeIs('admin.plugins.store.products.archived');
@endphp
<main class="z4-store-admin z4-store-products-page" data-z4-store-foundation data-store-products-page>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_6a55e15dae') }}</span>
                <h1>{{ $isArchivedView ? __('store::ui.admin_products_deleted') : __('store::ui.admin_products_products') }}</h1>
                <p>{{ $isArchivedView ? __('store::ui.admin_products_deleted_desc') : __('store::ui.admin_products_desc') }}</p>
            </div>
            @if($isArchivedView)
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.index') }}">{{ __('store::ui.auto_a40de094eb') }}</a>
            @else
                <div class="z4-store-heading-actions">
                    @can('store.products.view')
                        <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.export') }}">{{ __('store::ui.auto_c04f1e4841') }}</a>
                    @endcan
                    @can('store.products.create')
                        <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.import.create') }}">{{ __('store::ui.auto_28ec2d08ef') }}</a>
                        <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.products.create') }}">
                            <span aria-hidden="true">＋</span> {{ __('store::ui.auto_512b2b14cb') }}
                        </a>
                    @endcan
                </div>
            @endif
        </header>

        @if(! $isArchivedView)
            <section class="z4-store-stat-grid" aria-label="{{ __('store::ui.auto_e7bd3fef06') }}">
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-purple">▤</span><div><small>{{ __('store::ui.auto_40dec54641') }}</small><strong>{{ $metrics['all'] }}</strong><span>{{ __('store::ui.auto_e9f14e011d') }}</span></div></article>
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-green">✓</span><div><small>{{ __('store::ui.auto_9b9d8a976b') }}</small><strong>{{ $metrics['published'] }}</strong><span>{{ __('store::ui.auto_36e3728d19') }}</span></div></article>
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-orange">◫</span><div><small>{{ __('store::ui.auto_db3af42ba6') }}</small><strong>{{ $metrics['drafts'] }}</strong><span>{{ __('store::ui.auto_64c62f292b') }}</span></div></article>
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-blue">◌</span><div><small>{{ __('store::ui.auto_b55197a49e') }}</small><strong>{{ $metrics['outOfStock'] }}</strong><span>{{ __('store::ui.auto_d6f5722e46') }}</span></div></article>
            </section>
        @endif

        <form class="z4-store-filter-card" method="get">
            <label class="z4-store-search"><span>{{ __('store::ui.auto_13348442cc') }}</span><input class="z4-store-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('store::ui.auto_b1b820c598') }}"><i aria-hidden="true">⌕</i></label>
            <label><span>{{ __('store::ui.auto_3adbdb3ac0') }}</span><select class="z4-store-select" name="category"><option value="">{{ __('store::ui.auto_62f5d708d6') }}</option>@foreach($categories as $category)<option value="{{ $category->public_id }}" @selected(($filters['category'] ?? '') === $category->public_id)>{{ $category->name }}</option>@endforeach</select></label>
            <label><span>{{ __('store::ui.auto_1be6f9eb56') }}</span><select class="z4-store-select" name="brand"><option value="">{{ __('store::ui.auto_e18a22d145') }}</option>@foreach($brands as $brand)<option value="{{ $brand->public_id }}" @selected(($filters['brand'] ?? '') === $brand->public_id)>{{ $brand->name }}</option>@endforeach</select></label>
            @if($isArchivedView)
                <input type="hidden" name="status" value="archived">
            @else
                <label>
                    <span>{{ __('store::ui.auto_ec53a8c4f0') }}</span>
                    <select class="z4-store-select" name="status">
                        <option value="">{{ __('store::ui.auto_0dff68feb2') }}</option>
                        @foreach(['draft' => __('store::ui.admin_products_status_draft'), 'published' => __('store::ui.admin_products_status_published'), 'hidden' => __('store::ui.admin_products_status_hidden'), 'archived' => __('store::ui.admin_products_status_archived')] as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected(($filters['status'] ?? '') === $statusValue)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label><span>{{ __('store::ui.auto_27ce7f8b56') }}</span><select class="z4-store-select" name="stock"><option value="">{{ __('store::ui.auto_64eea0e6fe') }}</option><option value="in_stock" @selected(($filters['stock'] ?? '') === 'in_stock')>{{ __('store::ui.auto_fcebe56087') }}</option><option value="out_of_stock" @selected(($filters['stock'] ?? '') === 'out_of_stock')>{{ __('store::ui.auto_b55197a49e') }}</option><option value="not_configured" @selected(($filters['stock'] ?? '') === 'not_configured')>{{ __('store::ui.auto_db3f921088') }}</option></select></label>
            <label><span>{{ __('store::ui.auto_33d8042bd7') }}</span><select class="z4-store-select" name="sort">@foreach(['published_desc' => __('store::ui.admin_products_sort_published_desc'), 'published_asc' => __('store::ui.admin_products_sort_published_asc'), 'updated_desc' => __('store::ui.admin_products_sort_updated_desc'), 'name' => __('store::ui.admin_products_sort_name'), 'price_asc' => __('store::ui.admin_products_sort_price_asc'), 'price_desc' => __('store::ui.admin_products_sort_price_desc')] as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'published_desc') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="z4-store-filter-actions"><button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_9639e32cab') }}</button><a class="z4-store-btn z4-store-btn-ghost" href="{{ $isArchivedView ? route('admin.plugins.store.products.archived') : route('admin.plugins.store.products.index') }}">{{ __('store::ui.auto_526d688f37') }}</a></div>
        </form>

        <section class="z4-store-table-card">
            <div class="z4-store-table-scroll">
                <table class="z4-store-table">
                    <thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>SKU</th><th>{{ __('store::ui.auto_3adbdb3ac0') }}</th><th>{{ __('store::ui.auto_1be6f9eb56') }}</th><th>{{ __('store::ui.auto_3601146c4e') }}</th><th>{{ __('store::ui.auto_27ce7f8b56') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th>{{ __('store::ui.auto_9b9d8a976b') }}</th><th><span class="sr-only">{{ __('store::ui.auto_06df33001c') }}</span></th></tr></thead>
                    <tbody>
                    @forelse($products as $product)
                        @php
                            $displayStatus = $product->status === 'archived' ? 'archived' : ($product->status === 'draft' ? 'draft' : ($product->visibility === 'hidden' ? 'hidden' : 'published'));
                            $available = $product->tracking_mode === 'tracked' ? ((int) $product->on_hand - (int) $product->reserved) : null;
                            $stockStatus = $product->tracking_mode === null ? 'not-configured' : (($product->tracking_mode === 'untracked' || $available > 0) ? 'in-stock' : 'out-of-stock');
                            $price = $product->sale_price_minor ?? $product->regular_price_minor;
                            $artworkEditable = $product->type_code === 'artwork' && in_array($product->artwork_workflow_state, ['draft', 'changes_required', 'rejected'], true);
                            $artworkReviewable = $product->type_code === 'artwork' && in_array($product->artwork_workflow_state, ['submitted', 'under_review'], true);
                            $productPrimaryUrl = $product->type_code !== 'artwork'
                                ? route('admin.plugins.store.products.edit', $product->public_id)
                                : ($artworkEditable
                                    ? route('admin.plugins.store.artworks.edit', $product->public_id)
                                    : route('admin.plugins.store.products.view', $product->public_id));
                        @endphp
                        <tr>
                            <td data-label="Product"><div class="z4-store-product-cell">
                                @if($product->cover_path)<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($product->cover_path) }}" alt="">@else<span class="z4-store-product-fallback">{{ strtoupper(\Illuminate\Support\Str::substr($product->name ?: 'P', 0, 2)) }}</span>@endif
                                <div><a href="{{ $productPrimaryUrl }}">{{ $product->name ?? $product->public_id }}</a><small>{{ __('store::ui.auto_93b16af310') }}</small></div>
                            </div></td>
                            <td data-label="SKU"><code>{{ $product->sku ?: '—' }}</code></td>
                            <td data-label="Category"><span class="z4-store-pill is-purple">{{ $product->category_name ?: 'Uncategorized' }}</span></td>
                            <td data-label="Brand">{{ $product->brand_name ?: '—' }}</td>
                            <td data-label="Price">
                                @if($price !== null)<strong>{{ app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $price) }}</strong>@if($product->sale_price_minor !== null && $product->regular_price_minor !== null)<small class="z4-store-old-price">{{ app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $product->regular_price_minor) }}</small>@endif
                                @else<span class="z4-store-muted">{{ __('store::ui.auto_9ba66a9f92') }}</span>@endif
                            </td>
                            <td data-label="Stock"><span class="z4-store-stock is-{{ $stockStatus }}"><i></i>{{ $stockStatus === 'in-stock' ? 'In stock'.($available !== null ? " ({$available})" : '') : ($stockStatus === 'out-of-stock' ? 'Out of stock' : 'Not configured') }}</span></td>
                            <td data-label="Status"><span class="z4-store-status is-{{ $displayStatus }}">{{ $displayStatus === 'archived' ? 'Deleted' : ucfirst($displayStatus) }}</span></td>
                            <td data-label="Published">@if($product->published_at)<strong>{{ \Illuminate\Support\Carbon::parse($product->published_at)->format('M d, Y') }}</strong><small>{{ \Illuminate\Support\Carbon::parse($product->published_at)->diffForHumans() }}</small>@else<span class="z4-store-muted">{{ __('store::ui.auto_d470b5cb00') }}</span>@endif</td>
                            <td class="z4-store-row-actions">
                                <details class="z4-store-actions-menu" data-action-menu>
                                    <summary aria-label="{{ __('store::ui.auto_9953b339fe') }}">•••</summary>
                                    <div role="menu">
                                        @if($product->status === 'published' && $product->visibility === 'public' && $product->slug)
                                            <a href="{{ route('store.products.show', $product->slug) }}" target="_blank" rel="noopener" role="menuitem">{{ __('store::ui.auto_4351cfebe4') }}</a>
                                        @else
                                            <span class="z4-store-actions-note" role="note">{{ __('store::ui.auto_3aadc2a770') }}</span>
                                        @endif
                                        @can('store.products.update')
                                            @if($product->type_code !== 'artwork')
                                                <a href="{{ route('admin.plugins.store.products.edit', $product->public_id) }}" role="menuitem">{{ __('store::ui.auto_7dce122004') }}</a>
                                            @elseif($artworkEditable)
                                                <a href="{{ route('admin.plugins.store.artworks.edit', $product->public_id) }}" role="menuitem">{{ __('store::ui.auto_431dc5fbdf') }}</a>
                                            @elseif($artworkReviewable)
                                                @can('store.artworks.review')
                                                    <a href="{{ route('admin.plugins.store.artworks.review.show', $product->public_id) }}" role="menuitem">{{ __('store::ui.auto_28a04779c2') }}</a>
                                                @endcan
                                            @elseif($product->artwork_workflow_state === 'approved' && $product->status === 'published')
                                                @can('store.products.publish')
                                                    <form method="post" action="{{ route('admin.plugins.store.artworks.unpublish-for-edit', $product->public_id) }}" data-z4-confirm data-confirm-title="{{ __('store::ui.auto_fe7c50970f') }}" data-confirm-message="Store will temporarily hide the artwork while you edit it. Your completed changes can be published directly without a review step." data-confirm-label="Edit artwork">
                                                        @csrf
                                                        <input type="hidden" name="confirmation" value="unpublish-for-edit">
                                                        <button role="menuitem">{{ __('store::ui.auto_431dc5fbdf') }}</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        @endcan
                                        @can('store.products.create')
                                            @if($product->type_code !== 'artwork')<form method="post" action="{{ route('admin.plugins.store.products.duplicate', $product->public_id) }}">@csrf<button role="menuitem">{{ __('store::ui.auto_ed75712b0e') }}</button></form>@endif
                                        @endcan
                                        @can('store.products.publish')
                                        @if($product->type_code !== 'artwork')
                                        @if($product->status === 'published')
                                            <form method="post" action="{{ route('admin.plugins.store.products.visibility', $product->public_id) }}">@csrf<input type="hidden" name="version" value="{{ $product->version }}"><input type="hidden" name="visibility" value="{{ $product->visibility === 'hidden' ? 'public' : 'hidden' }}"><button role="menuitem">{{ $product->visibility === 'hidden' ? 'Show in catalog' : 'Hide from catalog' }}</button></form>
                                        @endif
                                        @if($product->status === 'draft')
                                            <form method="post" action="{{ route('admin.plugins.store.products.publish', $product->public_id) }}">@csrf<input type="hidden" name="version" value="{{ $product->version }}"><button role="menuitem">{{ __('store::ui.auto_110a4b01be') }}</button></form>
                                        @endif
                                        @endif
                                        @endcan
                                        @can('store.products.archive')
                                        @if($product->status !== 'archived')
                                            @if($product->type_code === 'artwork')
                                            <form method="post" action="{{ route('admin.plugins.store.artworks.archive', $product->public_id) }}" data-z4-confirm data-confirm-title="{{ __('store::ui.auto_fd35759040') }}" data-confirm-message="The artwork will leave the catalog. Images, authenticity evidence, and history remain available for safe restoration." data-confirm-label="Delete artwork">@csrf<input type="hidden" name="confirmation" value="archive-artwork"><button class="is-danger" role="menuitem">{{ __('store::ui.auto_f2a6c498fb') }}</button></form>
                                            @else
                                            <form method="post" action="{{ route('admin.plugins.store.products.archive', $product->public_id) }}" data-z4-confirm data-confirm-title="{{ __('store::ui.auto_3fc8a96873') }}" data-confirm-message="The product will leave the active catalog, while its data and history remain available for restoration." data-confirm-label="Delete product">@csrf<input type="hidden" name="version" value="{{ $product->version }}"><button class="is-danger" role="menuitem">{{ __('store::ui.auto_f2a6c498fb') }}</button></form>
                                            @endif
                                        @else
                                            @if($product->type_code === 'artwork')
                                            <form method="post" action="{{ route('admin.plugins.store.artworks.restore', $product->public_id) }}">@csrf<input type="hidden" name="confirmation" value="restore-artwork"><button role="menuitem">{{ __('store::ui.auto_3627510324') }}</button></form>
                                            @else
                                            <form method="post" action="{{ route('admin.plugins.store.products.restore', $product->public_id) }}">@csrf<input type="hidden" name="version" value="{{ $product->version }}"><button role="menuitem">{{ __('store::ui.auto_2bd339d85e') }}</button></form>
                                            @endif
                                        @endif
                                        @endcan
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="z4-store-empty"><span>▤</span><strong>{{ $isArchivedView ? __('store::ui.admin_products_none_deleted') : __('store::ui.admin_products_none') }}</strong><p>{{ $isArchivedView ? __('store::ui.admin_products_none_deleted_desc') : __('store::ui.admin_products_none_desc') }}</p><a class="z4-store-btn z4-store-btn-primary" href="{{ $isArchivedView ? route('admin.plugins.store.products.index') : route('admin.plugins.store.products.create') }}">{{ $isArchivedView ? __('store::ui.admin_products_all') : __('store::ui.admin_products_add') }}</a></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->total() > 0)
                <footer class="z4-store-table-footer">
                    <form method="get">@foreach(request()->except(['per_page','page']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label>{{ __('store::ui.auto_498f79c4c5') }} <select class="z4-store-select" name="per_page" onchange="this.form.submit()">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($products->perPage() === $size)>{{ $size }}</option>@endforeach</select></label></form>
                    <span>{{ __('store::ui.auto_593d951d49') }}</span>
                    <x-platform-pagination :paginator="$products" />
                </footer>
            @endif
        </section>
    </div>
</main>
<script>
document.addEventListener('click', function (event) {
    document.querySelectorAll('[data-action-menu][open]').forEach(function (menu) {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
</script>
</x-app-layout>
