<?php

return [

    'canonical' => [
        // Fixed canonical host, for example https://www.artinpa.com.
        // Leave empty during local/demo work so the request host is used.
        'host' => env('SEO_CANONICAL_HOST'),
    ],

    'head' => [
        'canonical' => env('SEO_HEAD_CANONICAL', true),
        'alternates' => env('SEO_HEAD_ALTERNATES', true),
    ],

    'sitemap' => [
        'cache_ttl' => (int) env('SEO_SITEMAP_CACHE_TTL', 300),
        'max_urls' => (int) env('SEO_SITEMAP_MAX_URLS', 50000),
        'max_bytes' => (int) env('SEO_SITEMAP_MAX_BYTES', 52428800),
        'lazy_generate' => (bool) env('SEO_SITEMAP_LAZY_GENERATE', true),
        // Keep only the most recent sitemap runs in seo_sitemap_runs.
        'keep_runs' => (int) env('SEO_SITEMAP_KEEP_RUNS', 20),
    ],

    'audit' => [
        'max_urls' => (int) env('SEO_AUDIT_MAX_URLS', 100),
        'timeout' => (int) env('SEO_AUDIT_TIMEOUT', 10),
        'user_agent' => (string) env('SEO_AUDIT_USER_AGENT', 'ArtINPA-SEO-Audit/1.0'),
        // Optional internal base URL for HTTP checks in Docker/local installs
        // where the public host/port is not reachable from the app container.
        'base_url' => env('SEO_AUDIT_BASE_URL'),
    ],

    'redirects' => [
        'cache_ttl' => (int) env('SEO_REDIRECT_CACHE_TTL', 300),
        'max_hops' => (int) env('SEO_REDIRECT_MAX_HOPS', 5),
        // Comma-separated exact hosts or wildcard domains, for example:
        // example.com,*.example.org
        'external_allowlist' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SEO_REDIRECT_EXTERNAL_ALLOWLIST', '')),
        ))),
    ],

    'robots' => [
        'disallow' => [
            '/admin',
            '/dashboard',
            '/account',
            '/api',
            '/install',
            '/up',
            '/store/cart',
            '/store/checkout',
            '/store/search',
        ],
    ],

    'retention' => [
        'uninstall_days' => (int) env('SEO_RETENTION_UNINSTALL_DAYS', 90),
        'orphaned_days' => (int) env('SEO_RETENTION_ORPHANED_DAYS', 30),
        'audit_days' => (int) env('SEO_RETENTION_AUDIT_DAYS', 365),
    ],

    'locale' => [
        // The platform setting general.site_language is the source of truth.
        // This value is only a fallback for installations without that row.
        'default' => env('SEO_DEFAULT_LOCALE'),
        'mode' => env('SEO_LOCALE_MODE', 'query'),
    ],

    'noindex' => [
        'paths' => [
            'admin',
            'admin/*',
            'dashboard',
            'account',
            'account/*',
            'login',
            'register',
            'password/*',
            'verify-email',
            'verify-email/*',
            'store/cart',
            'store/checkout',
            'store/search',
            'store/account',
            'store/account/*',
            'up',
            'install',
            'install/*',
            'api/*',
            'courses/*/preview*',
            'courses/*/player*',
            'my-learning',
            'my-learning/*',
            'instructor-dashboard',
            'instructor-dashboard/*',
            'blog/writer',
            'blog/writer/*',
        ],
        'routes' => [
            'login',
            'register',
            'password.*',
            'verification.*',
            'dashboard',
            'front.account',
            'store.cart.*',
            'store.checkout.*',
            'store.search',
            'store.account.*',
            'lms.front.courses.player',
            'lms.front.courses.preview',
            'blog.writer.*',
            'admin.*',
        ],
    ],

];
