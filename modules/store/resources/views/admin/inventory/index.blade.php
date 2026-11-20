<x-app-layout>
<main class="z4-store-admin" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_a17f6641cb') }}</span>
                <h1>{{ __('store::ui.auto_4d604dd8f0') }}</h1>
                <p>{{ __('store::ui.auto_5c55f543af') }}</p>
            </div>
        </header>

        <section class="z4-store-stat-grid" aria-label="{{ __('store::ui.auto_e0b01e7810') }}">
            <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-purple">&#9638;</span><div><small>{{ __('store::ui.auto_7ed9ed903e') }}</small><strong>{{ $inventoryMetrics['total'] }}</strong><span>{{ __('store::ui.auto_34d04e7537') }}</span></div></article>
            <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-green">&#10003;</span><div><small>{{ __('store::ui.auto_78945de8de') }}</small><strong>{{ $inventoryMetrics['available'] }}</strong><span>{{ __('store::ui.auto_a515e33aa5') }}</span></div></article>
            <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-orange">!</span><div><small>{{ __('store::ui.auto_b55197a49e') }}</small><strong>{{ $inventoryMetrics['out_of_stock'] }}</strong><span>{{ __('store::ui.auto_d6f5722e46') }}</span></div></article>
            <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-orange">!</span><div><small>{{ __('store::ui.auto_7e2525a11b') }}</small><strong>{{ $inventoryMetrics['low_stock'] }}</strong><span>{{ __('store::ui.auto_3469f8108f') }}</span></div></article>
        </section>

        @if($inventoryMetrics['total'] > 0)
            <section class="z4-store-table-card z4-store-inventory-browser">
                <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_7ed9ed903e') }}</h2><p>{{ __('store::ui.auto_ae7355f71c') }}</p></div><span>{{ $items->total() }} {{ \Illuminate\Support\Str::plural('item', $items->total()) }}</span></header>
                <form class="z4-store-filter-card z4-store-inventory-filters" method="get">
                    <input type="hidden" name="per_page" value="{{ $items->perPage() }}">
                    <label class="z4-store-search"><span>{{ __('store::ui.auto_437ac95a91') }}</span><input class="z4-store-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('store::ui.auto_b1b820c598') }}"><i aria-hidden="true">&#8981;</i></label>
                    <label><span>{{ __('store::ui.auto_0de9763b64') }}</span><select class="z4-store-select" name="stock">
                        <option value="">{{ __('store::ui.auto_64eea0e6fe') }}</option>
                        <option value="in_stock" @selected(($filters['stock'] ?? '') === 'in_stock')>{{ __('store::ui.auto_fcebe56087') }}</option>
                        <option value="low_stock" @selected(($filters['stock'] ?? '') === 'low_stock')>{{ __('store::ui.auto_7e2525a11b') }}</option>
                        <option value="out_of_stock" @selected(($filters['stock'] ?? '') === 'out_of_stock')>{{ __('store::ui.auto_b55197a49e') }}</option>
                        <option value="untracked" @selected(($filters['stock'] ?? '') === 'untracked')>{{ __('store::ui.auto_071b21852e') }}</option>
                    </select></label>
                    <div class="z4-store-filter-actions"><button class="z4-store-btn z4-store-btn-primary">{{ __('store::ui.auto_9639e32cab') }}</button><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.inventory.index') }}">{{ __('store::ui.auto_526d688f37') }}</a></div>
                </form>
                @if($items->isNotEmpty())
                <div class="z4-store-table-scroll"><table class="z4-store-table z4-store-table-compact"><thead><tr><th>{{ __('store::ui.auto_deb1051765') }}</th><th>{{ __('store::ui.auto_6fa2c797f7') }}</th><th>{{ __('store::ui.auto_78945de8de') }}</th><th>{{ __('store::ui.auto_51359e8b51') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th></th></tr></thead><tbody>
                    @foreach($items as $item)
                        @php($available = $item->on_hand - $item->reserved)
                        @php($isLowStock = $item->tracking_mode === 'tracked' && $available > 0 && $available <= $item->safety_stock)
                        <tr class="{{ $selectedItem?->public_id === $item->public_id ? 'is-selected' : '' }}"><td><strong>{{ $item->product_name ?? 'Untitled product' }}</strong></td><td>{{ $item->sku ?: 'No SKU' }}</td><td>{{ $item->tracking_mode === 'untracked' ? 'Always in stock' : $available }}</td><td>{{ $item->tracking_mode === 'tracked' ? 'Tracked' : 'Untracked' }}</td><td><span class="z4-store-stock is-{{ $item->tracking_mode === 'untracked' || $available > 0 ? 'in-stock' : 'out-of-stock' }}{{ $isLowStock ? ' is-low-stock' : '' }}"><i></i>{{ $item->tracking_mode === 'untracked' ? 'In stock' : ($available <= 0 ? 'Out of stock' : ($isLowStock ? 'Low stock' : 'In stock')) }}</span></td><td><a class="z4-store-btn z4-store-btn-ghost z4-store-btn-small" href="{{ route('admin.plugins.store.inventory.index', array_merge(request()->query(), ['item' => $item->public_id])) }}#item-{{ $item->public_id }}">{{ __('store::ui.auto_34e34c43ec') }}</a></td></tr>
                    @endforeach
                </tbody></table></div>
                <footer class="z4-store-table-footer">
                    <form method="get">
                        @foreach(request()->except(['per_page', 'page']) as $key => $value)
                            @if(is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label>{{ __('store::ui.auto_498f79c4c5') }}
                            <select class="z4-store-select" name="per_page" onchange="this.form.submit()">
                                @foreach([10, 25, 50] as $size)
                                    <option value="{{ $size }}" @selected($items->perPage() === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                    <span>Showing {{ $items->firstItem() }}&ndash;{{ $items->lastItem() }} of {{ $items->total() }}</span>
                    <x-platform-pagination :paginator="$items" />
                </footer>
                @else
                    <div class="z4-store-empty"><span>&#9678;</span><strong>{{ __('store::ui.auto_a1d5a2d405') }}</strong><p>{{ __('store::ui.auto_5024ccc109') }}</p><a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.inventory.index') }}">{{ __('store::ui.auto_937d34bdd5') }}</a></div>
                @endif
            </section>

            @if($selectedItem)
                @php($item = $selectedItem)
                @php($available = $item->on_hand - $item->reserved)
                @php($isLowStock = $item->tracking_mode === 'tracked' && $available > 0 && $available <= $item->safety_stock)
                <article id="item-{{ $item->public_id }}" class="z4-store-form-card z4-store-inventory-card">
                    <header class="z4-store-inventory-header">
                        <div>
                            <span class="z4-store-eyebrow">{{ __('store::ui.auto_970f642652') }}</span>
                            <h2>{{ $item->product_name ?? $item->variant_public_id }}</h2>
                            <div class="z4-store-inventory-balances" aria-label="{{ __('store::ui.auto_35d7282af0') }}">
                                <div><strong>{{ $item->on_hand }}</strong><span>{{ __('store::ui.auto_b3fd3f6882') }}</span><small>{{ __('store::ui.auto_973c4e62a3') }}</small></div>
                                <div><strong>{{ $item->reserved }}</strong><span>{{ __('store::ui.auto_942d4e37dd') }}</span><small>{{ __('store::ui.auto_3cb7de3268') }}</small></div>
                                <div><strong>{{ $available }}</strong><span>{{ __('store::ui.auto_2bae1555cf') }}</span><small>{{ __('store::ui.auto_0fc8c55f4d') }}</small></div>
                            </div>
                        </div>
                        <span class="z4-store-stock is-{{ $item->tracking_mode === 'untracked' || $available > 0 ? 'in-stock' : 'out-of-stock' }}{{ $isLowStock ? ' is-low-stock' : '' }}"><i></i>{{ $item->tracking_mode === 'untracked' ? 'Always In Stock' : ($available <= 0 ? 'Out of Stock' : ($isLowStock ? 'Low Stock' : 'In Stock')) }}</span>
                    </header>

                    <div class="z4-store-split-grid">
                        <form method="post" action="{{ route('admin.plugins.store.inventory.policy', $item->public_id) }}" class="z4-store-subcard z4-store-form-grid">
                            @csrf @method('PATCH')
                            <input type="hidden" name="version" value="{{ $item->version }}">
                            <div class="z4-store-section-heading is-wide"><h3>{{ __('store::ui.auto_a54a0e8a7a') }}</h3><p>{{ __('store::ui.auto_4b1d977ed9') }}</p></div>
                            <label>
                                <span>{{ __('store::ui.auto_ef22d38df1') }}</span>
                                <select class="z4-store-select" name="tracking_mode">
                                    <option value="tracked" @selected($item->tracking_mode === 'tracked')>{{ __('store::ui.auto_e83b585e98') }}</option>
                                    <option value="untracked" @selected($item->tracking_mode === 'untracked')>{{ __('store::ui.auto_d9272d1dc1') }}</option>
                                </select>
                                <small>{{ __('store::ui.auto_cfecdea52e') }}</small>
                            </label>
                            <label>
                                <span>{{ __('store::ui.auto_607bfe46f6') }}</span>
                                <input type="number" min="0" class="z4-store-input" name="safety_stock" value="{{ $item->safety_stock }}">
                                <small>{{ __('store::ui.auto_9291c31973') }}</small>
                            </label>
                            <label>
                                <span>{{ __('store::ui.auto_1285900aa4') }}</span>
                                <input type="number" min="0" class="z4-store-input" name="reorder_point" value="{{ $item->reorder_point }}">
                                <small>{{ __('store::ui.auto_897c2adb87') }}</small>
                            </label>
                            <label class="z4-store-check z4-store-check-explained">
                                <input type="checkbox" name="allow_backorder" value="1" @checked($item->allow_backorder)>
                                <span><strong>{{ __('store::ui.auto_1768712b77') }}</strong><small>{{ __('store::ui.auto_50b88bc829') }}</small></span>
                            </label>
                            <div class="z4-store-form-actions is-wide"><button class="z4-store-btn z4-store-btn-ghost" data-submit-button>{{ __('store::ui.auto_877cf9b0df') }}</button></div>
                        </form>

                        <form
                            method="post"
                            action="{{ route('admin.plugins.store.inventory.adjust', $item->public_id) }}"
                            class="z4-store-subcard z4-store-form-grid"
                            data-inventory-adjustment
                            data-current-on-hand="{{ $item->on_hand }}"
                            data-reserved="{{ $item->reserved }}"
                            data-backorders="{{ $item->allow_backorder ? 'true' : 'false' }}"
                            data-z4-confirm
                            data-confirm-title="{{ __('store::ui.auto_c67e33e459') }}"
                            data-confirm-message="Review the quantity change before saving."
                            data-confirm-label="Save stock change"
                        >
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <div class="z4-store-section-heading is-wide"><h3>{{ __('store::ui.auto_0667ba1657') }}</h3><p>{{ __('store::ui.auto_aeefc871ee') }}</p></div>
                            <label>
                                <span>{{ __('store::ui.auto_41316f1a5d') }}</span>
                                <input type="number" class="z4-store-input" name="delta" placeholder="{{ __('store::ui.auto_eb75f4631e') }}" required data-inventory-delta>
                                <small>{{ __('store::ui.auto_2762e59aea') }}</small>
                            </label>
                            <label>
                                <span>{{ __('store::ui.auto_a062c8b187') }}</span>
                                <select class="z4-store-select" name="reason_code">
                                    <option value="stock_receipt">{{ __('store::ui.auto_fed4092388') }}</option>
                                    <option value="count_correction">{{ __('store::ui.auto_c881e22fb5') }}</option>
                                    <option value="damage">{{ __('store::ui.auto_ba5a070490') }}</option>
                                    <option value="manual_adjustment">{{ __('store::ui.auto_af24d1aa3c') }}</option>
                                </select>
                            </label>
                            <div class="z4-store-stock-preview is-wide" data-inventory-preview>
                                <div><span>{{ __('store::ui.auto_91024521d4') }}</span><strong>{{ $item->on_hand }}</strong></div>
                                <div><span>{{ __('store::ui.auto_f4ec5f57bd') }}</span><strong data-preview-change>0</strong></div>
                                <div><span>{{ __('store::ui.auto_2b7522f8a0') }}</span><strong data-preview-on-hand>{{ $item->on_hand }}</strong></div>
                                <div><span>{{ __('store::ui.auto_84bf3c8973') }}</span><strong data-preview-available>{{ $available }}</strong></div>
                            </div>
                            <p class="z4-store-stock-preview-warning is-wide" data-preview-warning aria-live="polite" hidden></p>
                            <label class="is-wide">
                                <span>{{ __('store::ui.auto_08643dacea') }} <small>{{ __('store::ui.auto_f53d1cd25e') }}</small></span>
                                <input class="z4-store-input" name="note" placeholder="{{ __('store::ui.auto_37a0c65690') }}">
                            </label>
                            <div class="z4-store-form-actions is-wide"><button class="z4-store-btn z4-store-btn-primary" data-stock-adjustment-submit data-submit-button>{{ __('store::ui.auto_59da8dcb02') }}</button></div>
                        </form>
                    </div>

                    <section class="z4-store-inventory-check">
                        <div>
                            <strong>{{ __('store::ui.auto_5af354b882') }}</strong>
                            <p>{{ __('store::ui.auto_86b848dc1e') }}</p>
                        </div>
                        <form method="post" action="{{ route('admin.plugins.store.inventory.reconcile', $item->public_id) }}">
                            @csrf
                            <button class="z4-store-btn z4-store-btn-ghost" data-submit-button>{{ __('store::ui.auto_b9b572bd03') }}</button>
                        </form>
                    </section>

                    <details class="z4-store-history">
                        <summary>{{ __('store::ui.auto_425dd0af39') }} <span>{{ __('store::ui.auto_a2f8361611') }}</span></summary>
                        <div class="z4-store-table-scroll"><table class="z4-store-table z4-store-table-compact"><thead><tr><th>{{ __('store::ui.auto_4e97aeeaa8') }}</th><th>{{ __('store::ui.auto_a1fa277792') }}</th><th>{{ __('store::ui.auto_db1f4ab584') }}</th><th>{{ __('store::ui.auto_99a808d8d1') }}</th><th>{{ __('store::ui.auto_fffcb4d1e1') }}</th><th>{{ __('store::ui.auto_1cc8461967') }}</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->occurred_at }}</td><td>{{ $movement->type }}</td><td>{{ $movement->quantity_delta }}</td><td>{{ $movement->balance_after }}</td><td>{{ $movement->reason_code }}</td><td>{{ $movement->actor_type }}:{{ $movement->actor_id }}</td></tr>@empty<tr><td colspan="6">{{ __('store::ui.auto_3e16bde45d') }}</td></tr>@endforelse</tbody></table></div>
                    </details>
                </article>
            @endif
        @else
            <section class="z4-store-table-card"><div class="z4-store-empty"><span>&#9678;</span><strong>{{ __('store::ui.auto_646446f93b') }}</strong><p>{{ __('store::ui.auto_55a46af4d2') }}</p><a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.products.index') }}">{{ __('store::ui.auto_a92ac165e9') }}</a></div></section>
        @endif
    </div>
</main>
<script>
document.querySelectorAll('[data-inventory-adjustment]').forEach(form => {
    const input = form.querySelector('[data-inventory-delta]');
    const changeOutput = form.querySelector('[data-preview-change]');
    const onHandOutput = form.querySelector('[data-preview-on-hand]');
    const availableOutput = form.querySelector('[data-preview-available]');
    const warning = form.querySelector('[data-preview-warning]');
    const submitButton = form.querySelector('[data-stock-adjustment-submit]');
    const currentOnHand = Number(form.dataset.currentOnHand);
    const reserved = Number(form.dataset.reserved);
    const allowsBackorders = form.dataset.backorders === 'true';

    const updatePreview = () => {
        const rawDelta = input.value.trim();
        const delta = Number(rawDelta || 0);
        const validDelta = Number.isInteger(delta) && delta !== 0;
        const newOnHand = currentOnHand + (Number.isFinite(delta) ? delta : 0);
        const available = newOnHand - reserved;
        const blocked = newOnHand < 0 && !allowsBackorders;

        changeOutput.textContent = delta > 0 ? `+${delta}` : String(delta);
        changeOutput.classList.toggle('is-positive', delta > 0);
        changeOutput.classList.toggle('is-negative', delta < 0);
        onHandOutput.textContent = String(newOnHand);
        availableOutput.textContent = String(available);
        warning.hidden = !blocked;
        warning.textContent = blocked ? 'This change would make physical stock negative. Reduce the quantity being removed.' : '';
        submitButton.disabled = !validDelta || blocked;
        form.dataset.confirmMessage = `Physical stock will change from ${currentOnHand} to ${newOnHand}. Available stock after active reservations will be ${available}.`;
    };

    input.addEventListener('input', updatePreview);
    updatePreview();
});

document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-button]').forEach(button => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
}));
</script>
</x-app-layout>
