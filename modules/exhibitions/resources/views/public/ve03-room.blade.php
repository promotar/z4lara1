@php($viewerVersion = filemtime(base_path('modules/exhibitions/resources/private-assets/js/ve03-public-viewer.bundle.js')))
@php($viewerStylesVersion = filemtime(base_path('modules/exhibitions/resources/private-assets/css/ve03-public-viewer.css')))
@php($transcoderUrl = route('exhibitions.virtual.ve03.transcoder', 'basis_transcoder.js'))
@php($viewerConfig = $viewerConfigOverride ?? [
    'manifest_url' => route('exhibitions.virtual.ve03.manifest', $room->slug),
    'telemetry_url' => route('exhibitions.virtual.ve03.telemetry', $room->slug),
    'availability_url' => route('exhibitions.virtual.ve03.availability', $room->slug),
    'transcoder_base_url' => substr($transcoderUrl, 0, -strlen('basis_transcoder.js')),
    'csrf_token' => csrf_token(),
    'cart_summary_url' => Route::has('store.cart.context.summary')
        ? route('store.cart.context.summary')
        : (Route::has('store.cart.items.store') ? route('exhibitions.virtual.ve03.cart.summary') : null),
    'cart_add_url' => Route::has('store.cart.context.items.store')
        ? route('store.cart.context.items.store')
        : (Route::has('store.cart.items.store') ? route('exhibitions.virtual.ve03.cart.items.store') : null),
    'cart_remove_url' => Route::has('store.cart.context.items.destroy')
        ? route('store.cart.context.items.destroy', '__VARIANT__')
        : (Route::has('store.cart.items.destroy') ? route('exhibitions.virtual.ve03.cart.items.destroy', '__VARIANT__') : null),
    'checkout_start_url' => Route::has('store.cart.context.checkout')
        ? route('store.cart.context.checkout')
        : (Route::has('store.checkout.index') ? route('exhibitions.virtual.ve03.cart.checkout') : null),
    'classic_cart_add_url' => Route::has('store.cart.items.store') ? route('store.cart.items.store') : null,
    'classic_cart_url' => Route::has('store.cart.index') ? route('store.cart.index') : null,
    'return_context' => $returnContext ?? null,
    'availability_poll_seconds' => (int) config('exhibitions.virtual_exhibitions.ve03.availability_poll_seconds', 25),
    'realtime' => [
        'enabled' => (bool) config('exhibitions.virtual_exhibitions.ve03.realtime.enabled', true),
        'key' => config('exhibitions.virtual_exhibitions.ve03.realtime.key'),
        'host' => config('exhibitions.virtual_exhibitions.ve03.realtime.public_host', 'localhost'),
        'port' => (int) config('exhibitions.virtual_exhibitions.ve03.realtime.public_port', 8089),
        'scheme' => config('exhibitions.virtual_exhibitions.ve03.realtime.public_scheme', 'http'),
        'channel' => 'exhibitions.ve03.'.$room->public_id,
        'event' => '.artwork.availability.changed',
    ],
])
@php($isAdminPreview = $isAdminPreview ?? false)
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $room->title }} · 3D Virtual Exhibition</title>
    <meta name="description" content="{{ $room->short_description }}">
    <link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}">
    <link rel="stylesheet" href="{{ route('exhibitions.virtual.ve03.stylesheet') }}?v={{ $viewerStylesVersion }}">
    <script src="{{ route('exhibitions.virtual.ve03.script') }}?v={{ $viewerVersion }}" defer></script>
</head>
<body class="z4-ve03-immersive-body">
<main class="z4-ve03-page">
    <header class="z4-ve03-header">
        <div>
            <span>{{ __('exhibitions::ui.free-walk-3d-exhibition') }}</span>
            <h1>{{ $room->title }}</h1>
            <p>{{ $room->short_description }}</p>
        </div>
        <nav class="z4-ve03-visitor-actions" aria-label="{{ __('exhibitions::ui.exhibition-navigation') }}">
            @if($isAdminPreview)
                <a href="{{ $previewBackUrl }}">{{ __('exhibitions::ui.back-to-review') }}</a>
            @else
            <a href="{{ route('exhibitions.virtual.index') }}">← العودة للمعارض</a>
            <a href="{{ route('exhibitions.artworks.index') }}">الأعمال الفنية</a>
            @endif
        </nav>
    </header>

    @if($isAdminPreview)
        <div class="z4-ve03-preview-banner" role="status">
            <strong>{{ __('exhibitions::ui.private-live-preview') }}</strong>
            <span>{{ __('exhibitions::ui.this-hall-is-visible-only-to-authorized-administrators-and-h') }}</span>
        </div>
    @endif

    <section class="z4-ve03-viewer" data-ve03-public-viewer data-viewer-state="booting" aria-label="{{ __('exhibitions::ui.free-walk-3d-virtual-exhibition') }}">
        <canvas data-ve03-canvas tabindex="0" aria-label="{{ __('exhibitions::ui.3d-exhibition-select-enter-exhibition-then-use-w-a-s-d-or-ar') }}"></canvas>
        <div class="z4-ve03-loading" data-ve03-loading role="status" aria-live="polite">
            <span aria-hidden="true"></span>
            <strong data-ve03-status>{{ __('exhibitions::ui.checking-3d-capabilities') }}</strong>
            <progress data-ve03-progress max="100" value="0">0%</progress>
        </div>
        <div class="z4-ve03-enter-panel" data-ve03-enter-panel hidden>
            <h2>{{ __('exhibitions::ui.explore-the-exhibition') }}</h2>
            <p>Move continuously with W/A/S/D. Arrow Up and Down move; Arrow Left and Right rotate. Right-click to release the mouse, then left-click the exhibition to resume camera control. Press Escape to pause.</p>
            <button type="button" data-ve03-enter disabled>{{ __('exhibitions::ui.enter-exhibition') }}</button>
        </div>
        <div class="z4-ve03-pause" data-ve03-pause hidden role="dialog" aria-modal="true" aria-labelledby="ve03-pause-title">
            <strong id="ve03-pause-title">{{ __('exhibitions::ui.movement-paused') }}</strong>
            <p>{{ __('exhibitions::ui.select-continue-to-return-to-the-exhibition') }}</p>
            <div class="z4-ve03-pause-actions">
                <button type="button" data-ve03-resume>{{ __('exhibitions::ui.continue') }}</button>
            </div>
        </div>
        <div class="z4-ve03-error" data-ve03-error hidden role="alert">
            <strong>{{ __('exhibitions::ui.3d-exhibition-unavailable') }}</strong>
            <p data-ve03-error-message>{{ __('exhibitions::ui.the-accessible-artwork-list-remains-available-from-the-artwo') }}</p>
            <button type="button" data-ve03-retry>{{ __('exhibitions::ui.retry') }}</button>
        </div>
        <div class="z4-ve03-hud" aria-label="{{ __('exhibitions::ui.exhibition-controls') }}">
            <output data-ve03-room aria-live="polite">{{ __('exhibitions::ui.loading-room') }}</output>
            <label>{{ __('exhibitions::ui.quality') }}
                <select data-ve03-quality aria-label="{{ __('exhibitions::ui.3d-quality') }}">
                    <option value="auto">{{ __('exhibitions::ui.auto') }}</option>
                    <option value="low">{{ __('exhibitions::ui.low') }}</option>
                    <option value="medium">{{ __('exhibitions::ui.medium') }}</option>
                    <option value="high">{{ __('exhibitions::ui.high') }}</option>
                </select>
            </label>
        </div>
        <button class="z4-ve03-cart-button" type="button" data-ve03-cart-open aria-controls="ve03-cart-drawer" aria-expanded="false">
            <span aria-hidden="true">{{ __('exhibitions::ui.cart') }}</span>
            <strong data-ve03-cart-count>0</strong>
            <span class="z4-ve03-sr-only">{{ __('exhibitions::ui.open-artwork-cart') }}</span>
        </button>
        <aside class="z4-ve03-cart-drawer" id="ve03-cart-drawer" data-ve03-cart-drawer hidden aria-labelledby="ve03-cart-title">
            <header>
                <div><span>{{ __('exhibitions::ui.your-exhibition-selection') }}</span><h2 id="ve03-cart-title">{{ __('exhibitions::ui.artwork-cart') }}</h2></div>
                <button type="button" data-ve03-cart-close aria-label="{{ __('exhibitions::ui.close-artwork-cart') }}">&times;</button>
            </header>
            <div class="z4-ve03-cart-feedback" data-ve03-cart-feedback role="status" aria-live="polite" hidden></div>
            <div class="z4-ve03-cart-items" data-ve03-cart-items></div>
            <footer data-ve03-cart-footer>
                <div><span>{{ __('exhibitions::ui.artwork-subtotal') }}</span><strong data-ve03-cart-subtotal>{{ __('exhibitions::ui.0-000-jod') }}</strong></div>
                <p data-ve03-cart-warning hidden>{{ __('exhibitions::ui.unavailable-artworks-must-be-removed-before-checkout') }}</p>
                <button type="button" data-ve03-checkout>{{ __('exhibitions::ui.proceed-to-checkout') }}</button>
                <small>{{ __('exhibitions::ui.you-will-continue-directly-to-secure-store-checkout') }}</small>
            </footer>
        </aside>
        <button class="z4-ve03-cart-backdrop" type="button" data-ve03-cart-backdrop hidden aria-label="{{ __('exhibitions::ui.close-artwork-cart') }}"></button>
        <div class="z4-ve03-mobile" data-ve03-mobile-control hidden>
            <div class="z4-ve03-joystick" data-ve03-joystick aria-label="{{ __('exhibitions::ui.movement-joystick') }}"><span data-ve03-stick></span></div>
            <div class="z4-ve03-look" data-ve03-look aria-label="{{ __('exhibitions::ui.drag-to-look-around') }}"></div>
        </div>
        <button class="z4-ve03-artwork-indicator" type="button" data-ve03-artwork-indicator hidden>Click to view artwork (E)</button>
        <div class="z4-ve03-live-status" data-ve03-live-status role="status" aria-live="polite" hidden></div>
        <dialog class="z4-ve03-artwork-popup" data-ve03-artwork-popup aria-label="{{ __('exhibitions::ui.artwork-details') }}">
            <header>
                <span>{{ __('exhibitions::ui.artwork-in-this-exhibition') }}</span>
                <button type="button" data-ve03-popup-close aria-label="{{ __('exhibitions::ui.close-artwork-details') }}">×</button>
            </header>
            <div data-ve03-popup-body></div>
        </dialog>
        <script type="application/json" data-ve03-config>{!! json_encode($viewerConfig, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
    </section>

    <p class="z4-ve03-instructions z4-ve03-sr-only">Mouse: look · Right-click: release cursor · Left-click exhibition: resume camera · W/A/S/D: move · Arrow Up/Down: move · Arrow Left/Right: turn · E/Enter: view artwork · F: fullscreen · Escape: pause</p>

    <details class="z4-ve03-accessible-drawer">
        <summary>قائمة الأعمال</summary>
        <section class="z4-ve03-accessible" aria-labelledby="ve03-artworks-heading">
            <header>
                <span>{{ __('exhibitions::ui.accessible-alternative') }}</span>
                <h2 id="ve03-artworks-heading">{{ __('exhibitions::ui.artworks-in-this-exhibition') }}</h2>
            </header>
            <div class="z4-ve03-artwork-list">
                @forelse($placements as $placement)
                    @php($art = $placement->viewer)
                    <article data-ve03-fallback-artwork data-placement-id="{{ $placement->public_id }}" data-artwork-id="{{ $art['artwork_public_id'] }}">
                        @if($art['media_url'])<img src="{{ $art['media_url'] }}" alt="{{ $art['title'] }}">@endif
                        <div><span data-ve03-fallback-availability>{{ $art['availability'] }}</span><h3>{{ $art['title'] }}</h3><p>{{ $art['artist'] }}</p><a data-ve03-fallback-action href="{{ $art['details_url'] }}">{{ __('exhibitions::ui.view-details-2') }}</a></div>
                    </article>
                @empty
                    <p>{{ __('exhibitions::ui.no-published-artworks-are-currently-installed-in-this-exhibi') }}</p>
                @endforelse
            </div>
        </section>
    </details>
</main>
</body>
</html>
