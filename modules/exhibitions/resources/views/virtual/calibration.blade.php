@php($viewerVersion = filemtime(base_path('modules/exhibitions/resources/private-assets/js/virtual-exhibition.js')))
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Native WebGL 360 Calibration</title>
    <link rel="stylesheet" href="{{ route('exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}">
    <script src="{{ route('exhibitions.assets.virtual-exhibition-script') }}?v={{ $viewerVersion }}" defer></script>
</head>
<body>
<main class="z4-ve-public-room">
    <header class="z4-ve-public-header">
        <div>
            <span>LOCAL / TESTING ONLY</span>
            <h1>{{ $room->title }}</h1>
            <p>{{ $room->short_description }}</p>
        </div>
        <p><strong>yaw 0:</strong> NORTH &nbsp; <strong>+yaw:</strong> EAST &nbsp; <strong>pitch 0:</strong> HORIZON &nbsp; <strong>+pitch:</strong> CEILING</p>
    </header>
    @php($viewerPanoramaUrl = route('exhibitions.virtual.calibration.panorama'))
    @php($viewerPlacements = $placements)
    @include('exhibitions::virtual._viewer')
    <p class="z4-ve-instructions">Calibration: drag upward to look at TOP / CEILING, downward to BOTTOM / FLOOR. Horizontal drag direction remains unchanged.</p>
</main>
</body>
</html>
