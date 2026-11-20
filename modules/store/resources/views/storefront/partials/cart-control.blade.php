@php
    $cartQuantity = max(0, (int) ($capacity['cart_quantity'] ?? 0));
    $maximumQuantity = max(0, (int) ($capacity['maximum_quantity'] ?? 0));
    $canAdd = (bool) ($capacity['can_add'] ?? false);
@endphp

<div
    class="z4sf-card-cart"
    data-store-cart-control
    data-variant-id="{{ $variantId }}"
    data-quantity="{{ $cartQuantity }}"
    data-maximum="{{ $maximumQuantity }}"
    data-add-url="{{ route('store.cart.items.store') }}"
    data-update-url="{{ route('store.cart.items.update', $variantId) }}"
    data-remove-url="{{ route('store.cart.items.destroy', $variantId) }}"
>
    <form method="post" action="{{ route('store.cart.items.store') }}" data-store-card-add-form @if($cartQuantity > 0) hidden @endif>
        @csrf
        <input type="hidden" name="variant_public_id" value="{{ $variantId }}">
        <input type="hidden" name="quantity" value="1">
        <button class="z4sf-card-add" type="submit" @disabled(! $available || ! $canAdd)>
            <span aria-hidden="true">+</span>
            {{ $available ? __('store::ui.store_auto_2d0f6b8300') : __('store::ui.store_auto_b55197a49e') }}
        </button>
    </form>

    <div class="z4sf-card-stepper" data-store-cart-stepper @if($cartQuantity === 0) hidden @endif>
        <button type="button" data-store-cart-minus aria-label="{{ __('store::ui.store_auto_809115cb4b') }}">−</button>
        <output data-store-cart-quantity aria-live="polite">{{ $cartQuantity }}</output>
        <button type="button" data-store-cart-plus aria-label="{{ __('store::ui.store_auto_67d4d7d41d') }}" @disabled(! $canAdd)>+</button>
    </div>
</div>
