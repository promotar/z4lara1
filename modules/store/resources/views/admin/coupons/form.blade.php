<x-app-layout>
@php
    $editing = $coupon !== null;
    $selectedProducts = old('product_ids', $targets->where('target_type', 'product')->pluck('target_public_id')->all());
    $selectedCategories = old('category_ids', $targets->where('target_type', 'category')->pluck('target_public_id')->all());
    $scope = old('target_scope', $targets->contains('target_type', 'product') ? 'product' : ($targets->contains('target_type', 'category') ? 'category' : 'all_catalog'));
    $discountType = old('discount_type', $coupon->action_type ?? 'percentage_discount');
    $discountValue = old('discount_value', $editing ? ($discountType === 'percentage_discount' ? number_format($coupon->percentage_basis_points / 100, 2, '.', '') : number_format($coupon->amount_minor / 1000, 3, '.', '')) : '');
@endphp
<main class="z4-store-admin" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_cc4616c718') }}</span>
                <h1>{{ $editing ? __('store::ui.coupon_edit_title') : __('store::ui.coupon_add_title') }}</h1>
                <p>{{ __('store::ui.auto_0b66b8d931') }}</p>
            </div>
            <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.coupons.index') }}">{{ __('store::ui.auto_81cb5ae956') }}</a>
        </header>

        <form method="post" action="{{ $editing ? route('admin.plugins.store.coupons.update', $coupon->public_id) : route('admin.plugins.store.coupons.store') }}" data-dirty-form>
            @csrf
            @if($editing) @method('PUT') <input type="hidden" name="version" value="{{ $coupon->version }}"> @endif

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>01</span><div><h2>{{ __('store::ui.auto_aab80e1027') }}</h2><p>{{ __('store::ui.auto_da93047223') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_02e12c5c98') }} <b>*</b></span><input class="z4-store-input" name="name" required maxlength="255" value="{{ old('name', $coupon->name ?? '') }}"></label>
                    <label><span>{{ __('store::ui.auto_9d463ea3fe') }} <b>*</b></span><input class="z4-store-input" name="code" required maxlength="64" autocomplete="off" value="{{ old('code', $coupon->code_normalized ?? '') }}" placeholder="{{ __('store::ui.auto_47321f76b4') }}"></label>
                </div>
            </section>

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>02</span><div><h2>{{ __('store::ui.auto_d8f7f6908d') }}</h2><p>{{ __('store::ui.coupon_discount_note') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_f908785bb6') }}</span><select class="z4-store-select" name="discount_type"><option value="percentage_discount" @selected($discountType === 'percentage_discount')>{{ __('store::ui.auto_59e9811009') }}</option><option value="fixed_amount_discount" @selected($discountType === 'fixed_amount_discount')>{{ __('store::ui.auto_d42d734ecd') }}</option></select></label>
                    <label><span>{{ __('store::ui.auto_ac1910d0de') }} <b>*</b></span><input class="z4-store-input" name="discount_value" required value="{{ $discountValue }}" placeholder="10.00"></label>
                    <label><span>{{ __('store::ui.auto_4284efe6bf') }}</span><input class="z4-store-input" name="minimum_order" value="{{ old('minimum_order', $editing ? number_format($coupon->minimum_subtotal_minor / 1000, 3, '.', '') : '') }}" placeholder="0.000"></label>
                </div>
            </section>

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>03</span><div><h2>{{ __('store::ui.auto_0c8dc99954') }}</h2><p>{{ __('store::ui.auto_759491e676') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_eb772dc590') }}</span><input type="number" min="1" class="z4-store-input" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit ?? '') }}"></label>
                    <label><span>{{ __('store::ui.auto_4197d58e30') }}</span><input type="number" min="1" class="z4-store-input" name="per_customer_limit" value="{{ old('per_customer_limit', $coupon->per_customer_limit ?? '') }}"></label>
                </div>
            </section>

            <section class="z4-store-form-card" data-coupon-eligibility>
                <header class="z4-store-section-heading"><span>04</span><div><h2>{{ __('store::ui.auto_b5076520d9') }}</h2><p>{{ __('store::ui.auto_3a893a2b63') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_2656d7b911') }}</span><select class="z4-store-select" name="target_scope" data-coupon-scope><option value="all_catalog" @selected($scope === 'all_catalog')>{{ __('store::ui.auto_a40de094eb') }}</option><option value="product" @selected($scope === 'product')>{{ __('store::ui.auto_fd34832544') }}</option><option value="category" @selected($scope === 'category')>{{ __('store::ui.auto_22df8e3ca5') }}</option></select></label>
                    <label class="is-wide" data-coupon-target="product"><span>{{ __('store::ui.auto_d8b7861fb5') }}</span><select class="z4-store-select" name="product_ids[]" multiple size="8">@foreach($products as $product)<option value="{{ $product->public_id }}" @selected(in_array($product->public_id, $selectedProducts, true))>{{ $product->name ?: $product->public_id }}</option>@endforeach</select></label>
                    <label class="is-wide" data-coupon-target="category"><span>{{ __('store::ui.auto_55071098da') }}</span><select class="z4-store-select" name="category_ids[]" multiple size="8">@foreach($categories as $category)<option value="{{ $category->public_id }}" @selected(in_array($category->public_id, $selectedCategories, true))>{{ $category->name ?: $category->public_id }}</option>@endforeach</select></label>
                </div>
            </section>

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>05</span><div><h2>{{ __('store::ui.auto_28e2d1f5af') }}</h2><p>{{ __('store::ui.auto_5613235c3d') }}</p></div></header>
                <div class="z4-store-form-grid">
                    <label><span>{{ __('store::ui.auto_3009c5aa71') }}</span><input type="datetime-local" class="z4-store-input" name="starts_at" value="{{ old('starts_at', $coupon?->starts_at ? \Illuminate\Support\Carbon::parse($coupon->starts_at)->format('Y-m-d\TH:i') : '') }}"></label>
                    <label><span>{{ __('store::ui.auto_e36ff97e0a') }}</span><input type="datetime-local" class="z4-store-input" name="ends_at" value="{{ old('ends_at', $coupon?->ends_at ? \Illuminate\Support\Carbon::parse($coupon->ends_at)->format('Y-m-d\TH:i') : '') }}"></label>
                    <label class="z4-store-check is-wide"><input type="checkbox" name="active" value="1" @checked(old('active', $coupon?->status === 'active'))><span><strong>{{ __('store::ui.auto_4d3d769b81') }}</strong><small>{{ __('store::ui.auto_5c2e59d889') }}</small></span></label>
                </div>
            </section>

            <div class="z4-store-form-actions">
                <button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ $editing ? __('store::ui.coupon_save') : __('store::ui.coupon_create') }}</button>
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.coupons.index') }}">{{ __('store::ui.auto_ea4788705e') }}</a>
            </div>
        </form>
    </div>
</main>
<script>
(() => {
    const scope = document.querySelector('[data-coupon-scope]');
    const refresh = () => document.querySelectorAll('[data-coupon-target]').forEach(group => {
        group.hidden = group.dataset.couponTarget !== scope.value;
        group.querySelector('select').disabled = group.hidden;
    });
    scope?.addEventListener('change', refresh);
    refresh();
})();
</script>
</x-app-layout>
