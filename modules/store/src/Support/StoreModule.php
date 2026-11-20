<?php

namespace Modules\Store\Support;

final class StoreModule
{
    public const SLUG = 'store';

    public const DEFAULT_PAYMENT_METHOD = 'cash_on_delivery';

    /** @var list<string> */
    public const CAPABILITIES = [
        'store.access',
        'store.manage',
        'store.settings.view',
        'store.settings.update',
        'store.catalog.products.read',
        'store.catalog.products.manage',
        'store.catalog.products.publish',
        'store.integrations.external-sellables.v1',
        'store.integrations.external-sellable-lifecycle.v1',
        'store.catalog.variants.read',
        'store.catalog.variants.manage',
        'store.categories.read',
        'store.categories.manage',
        'store.attributes.read',
        'store.attributes.manage',
        'store.media.read',
        'store.media.associate',
        'store.pricing.read',
        'store.pricing.manage',
        'store.promotions.read',
        'store.promotions.manage',
        'store.inventory.read',
        'store.inventory.manage',
        'store.inventory.reserve',
        'store.cart',
        'store.checkout',
        'store.orders.create',
        'store.payments.cod',
    ];

    /** @var list<string> */
    public const PERMISSIONS = [
        'store.access',
        'store.manage',
        'store.settings.view',
        'store.settings.update',
        'store.catalog.view',
        'store.catalog.manage',
        'store.catalog.publish',
        'store.products.view',
        'store.products.create',
        'store.products.update',
        'store.products.publish',
        'store.products.archive',
        'store.products.delete',
        'store.categories.view',
        'store.categories.manage',
        'store.brands.view',
        'store.brands.manage',
        'store.attributes.view',
        'store.attributes.manage',
        'store.media.associate',
        'store.pricing.view',
        'store.pricing.manage',
        'store.promotions.view',
        'store.promotions.manage',
        'store.inventory.view',
        'store.inventory.manage',
        'store.inventory.reserve',
    ];

    private function __construct() {}
}
