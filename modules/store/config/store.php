<?php

return [
    /*
    | Runtime availability is owned by the platform plugin registry. This
    | configuration intentionally does not introduce a second enabled flag.
    */
    'default_currency' => env('STORE_DEFAULT_CURRENCY', 'JOD'),
    'default_country' => env('STORE_DEFAULT_COUNTRY', 'JO'),
    'default_payment_method' => 'cash_on_delivery',
    'orders' => [
        'confirmation' => 'manual',
        'auto_fulfill' => false,
        'shipping_minor' => 0,
        'statuses' => ['new', 'confirmed', 'processing', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'],
        'customer_email_statuses' => ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
        'cancellation_reasons' => [
            'customer_request',
            'out_of_stock',
            'invalid_customer_data',
            'unreachable_customer',
            'undeliverable',
            'other',
        ],
    ],
    'coupons' => [
        'release_usage_on_cancel' => true,
        'maximum_per_order' => 1,
    ],
    'storefront' => [
        'placeholder_enabled' => false,
        'products_per_page' => 24,
        'featured_limit' => 8,
        'related_limit' => 4,
    ],
    'catalog' => [
        'maximum_category_depth' => 8,
        'maximum_variants_per_product' => 100,
        'require_primary_media_for_publication' => false,
    ],
    'media' => [
        'maximum_upload_bytes' => 15_728_640,
        'maximum_image_dimension' => 12_000,
    ],
    'catalog_transfer' => [
        'maximum_csv_bytes' => 20_971_520,
        'maximum_rows' => 10_000,
        'export_chunk_size' => 100,
        'remote_image_connect_timeout' => 5,
        'remote_image_timeout' => 30,
        'remote_image_maximum_redirects' => 3,
    ],
    'artwork_media' => [
        'minimum_dimension' => 800,
        'maximum_dimension' => 2_500,
        'maximum_upload_bytes' => 20_971_520,
        'maximum_megapixels' => 80,
        'display_dimension' => 1_800,
        'thumbnail_dimension' => 480,
        'watermark_enabled' => false,
    ],
];
