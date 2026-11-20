@php
    $viewerMode = $viewerMode ?? 'public';
    $viewerPlacements = $viewerPlacements ?? [];
    $viewerConfig = [
        'panoramaUrl' => $viewerPanoramaUrl,
        'initialYaw' => (float) $room->initial_yaw,
        'initialPitch' => (float) $room->initial_pitch,
        'initialFov' => (float) $room->initial_fov,
        'minimumFov' => (float) $room->minimum_fov,
        'maximumFov' => (float) $room->maximum_fov,
        'autoRotate' => (bool) $room->auto_rotate_enabled,
        'autoRotateSpeed' => (float) $room->auto_rotate_speed,
        'autoRotateDelay' => (int) $room->auto_rotate_delay,
        'placements' => array_values($viewerPlacements),
    ];
@endphp
<section class="z4-ve-viewer" data-z4-virtual-viewer @if($viewerMode === 'layout') data-layout-editor @endif aria-label="360 degree virtual exhibition viewer">
    <canvas tabindex="0" aria-label="Drag to look around the 360 exhibition room. Use the wheel or zoom buttons to zoom."></canvas>
    <div class="z4-ve-overlays" data-viewer-overlays></div>
    <div class="z4-ve-loading" data-viewer-loading role="status"><span></span><strong>Loading the 360 exhibition room…</strong></div>
    <div class="z4-ve-error" data-viewer-error hidden role="alert"><strong>360 view unavailable</strong><p data-viewer-error-message>The room could not be rendered.</p><button type="button" onclick="location.reload()">Retry</button></div>
    <div class="z4-ve-controls" aria-label="Viewer controls">
        <button type="button" data-viewer-zoom-in aria-label="Zoom in">+</button>
        <button type="button" data-viewer-zoom-out aria-label="Zoom out">−</button>
        <button type="button" data-viewer-auto-rotate aria-label="Toggle auto-rotate" aria-pressed="{{ $room->auto_rotate_enabled ? 'true' : 'false' }}">↻</button>
        <button type="button" data-viewer-fullscreen aria-label="Toggle fullscreen">⛶</button>
    </div>
    <script type="application/json" data-viewer-config>{!! json_encode($viewerConfig, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
</section>
