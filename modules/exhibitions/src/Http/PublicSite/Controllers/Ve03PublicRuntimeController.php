<?php

namespace Modules\Exhibition\Http\PublicSite\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03ArtworkProjectionService;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03RuntimeManifestService;
use Modules\Exhibition\Application\VirtualExhibitions\VirtualExhibitionService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class Ve03PublicRuntimeController extends Controller
{
    private const TELEMETRY_EVENTS = [
        'exhibition_entered',
        'viewer_ready',
        'room_entered',
        'context_lost',
        'context_recovered',
        'viewer_error',
        'session_closed',
        'cart_item_added',
        'cart_item_removed',
        'cart_drawer_opened',
        'checkout_started',
    ];

    private const TELEMETRY_METRICS = [
        'quality_tier',
        'load_duration_ms',
        'session_duration_ms',
        'fps_average',
        'fps_low_1_percent',
        'draw_calls',
        'triangles',
        'textures',
        'gpu_memory_estimate_mb',
        'long_task_count',
        'long_task_duration_ms',
        'thermal_fps_change_percent',
        'room_code',
        'error_code',
    ];

    public function __construct(
        private readonly VirtualExhibitionService $rooms,
        private readonly Ve03RuntimeManifestService $runtime,
        private readonly Ve03ArtworkProjectionService $artworks,
    ) {}

    public function manifest(Request $request, string $room): JsonResponse
    {
        $record = $this->resolveRoom($room);
        $runtime = $this->runtime->activeForRoom($record);
        abort_unless($runtime, 404);

        $response = response()->json($runtime['manifest']);
        $response->setPublic()->setMaxAge(30)->setEtag($runtime['etag']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->isNotModified($request);

        return $response;
    }

    public function asset(string $hash, string $asset): BinaryFileResponse
    {
        try {
            $artifact = $this->runtime->publishedArtifact(strtolower($hash), $asset);
        } catch (DomainException) {
            abort(404);
        }

        return response()->file($artifact['path'], [
            'Content-Type' => 'model/gltf-binary',
            'Content-Length' => (string) $artifact['size'],
            'Content-Disposition' => 'inline; filename="'.$asset.'.glb"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.$artifact['hash'].'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function placementPopup(Request $request, string $room, string $placement): JsonResponse
    {
        [$record, $runtime] = $this->activeRuntime($room);
        try {
            $payload = $this->artworks->popup($record, $runtime['version'], $placement);
        } catch (DomainException) {
            abort(404);
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = response()->json($payload);
        $response->setPublic()->setMaxAge(30)->setEtag(hash('sha256', $encoded));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->isNotModified($request);

        return $response;
    }

    public function availability(string $room): JsonResponse
    {
        [$record, $runtime] = $this->activeRuntime($room);
        $payload = $this->artworks->availability($record, $runtime['version']);

        return response()->json($payload, 200, [
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function placementDerivative(string $room, string $placement, string $variant): BinaryFileResponse
    {
        [$record, $runtime] = $this->activeRuntime($room);
        try {
            $artifact = $this->artworks->derivative($record, $runtime['version'], $placement, $variant);
        } catch (DomainException) {
            abort(404);
        }

        return response()->file($artifact['path'], [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) $artifact['size'],
            'Content-Disposition' => 'inline; filename="artwork-'.$variant.'.jpg"',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.$artifact['hash'].'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function script(): BinaryFileResponse
    {
        return $this->moduleAsset('js/ve03-public-viewer.bundle.js', 'application/javascript; charset=UTF-8');
    }

    public function stylesheet(): BinaryFileResponse
    {
        return $this->moduleAsset('css/ve03-public-viewer.css', 'text/css; charset=UTF-8');
    }

    public function transcoder(string $asset): BinaryFileResponse
    {
        abort_unless(in_array($asset, ['basis_transcoder.js', 'basis_transcoder.wasm'], true), 404);
        $contentType = str_ends_with($asset, '.wasm') ? 'application/wasm' : 'application/javascript; charset=UTF-8';

        return $this->moduleAsset('ve03/basis/'.$asset, $contentType, true);
    }

    public function telemetry(Request $request, string $room): Response
    {
        $record = $this->resolveRoom($room);
        abort_unless($this->runtime->activeForRoom($record), 404);
        $validated = $request->validate([
            'event' => ['required', 'string', Rule::in(self::TELEMETRY_EVENTS)],
            'metrics' => ['sometimes', 'array'],
        ]);
        $metrics = array_intersect_key((array) ($validated['metrics'] ?? []), array_flip(self::TELEMETRY_METRICS));
        $metrics = array_filter($metrics, static fn (mixed $value): bool => is_string($value) || is_int($value) || is_float($value) || is_bool($value));

        Log::info('VE-03 public viewer telemetry.', [
            'event' => $validated['event'],
            'exhibition' => (string) $record->public_id,
            'metrics' => $metrics,
        ]);

        return response()->noContent();
    }

    private function moduleAsset(string $relativePath, string $contentType, bool $immutable = false): BinaryFileResponse
    {
        $path = dirname(__DIR__, 4).'/resources/private-assets/'.$relativePath;
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => $immutable ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0:object,1:array<string,mixed>} */
    private function activeRuntime(string $room): array
    {
        $record = $this->resolveRoom($room);
        $runtime = $this->runtime->activeForRoom($record);
        abort_unless($runtime, 404);

        return [$record, $runtime];
    }

    private function resolveRoom(string $room): object
    {
        $record = $this->runtime->publishedRoom($room);
        if ($record) {
            return $record;
        }

        try {
            return $this->rooms->liveRoom($room);
        } catch (DomainException) {
            abort(404);
        }
    }
}
