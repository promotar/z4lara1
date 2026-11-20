<?php

namespace Modules\Exhibition\Application\Store;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03RuntimeManifestService;
use Modules\Exhibition\Events\V1\PublicArtworkAvailabilityChanged;
use Throwable;

final readonly class Ve03AvailabilityPublisher
{
    public function __construct(private Ve03RuntimeManifestService $runtime) {}

    public function publish(string $artworkPublicId, int $version): void
    {
        $rooms = DB::table('exhibition_virtual_rooms')->where('status', 'published')->get();

        foreach ($rooms as $room) {
            try {
                $active = $this->runtime->activeForRoom($room);
                if (! $active) {
                    continue;
                }
                $items = collect($active['manifest']['placements'] ?? [])
                    ->filter(fn (array $placement): bool => hash_equals(
                        $artworkPublicId,
                        (string) ($placement['artwork']['id'] ?? ''),
                    ))
                    ->map(fn (array $placement): array => $this->sanitize($placement))
                    ->values()->all();
                if ($items === []) {
                    continue;
                }

                Event::dispatch(new PublicArtworkAvailabilityChanged(
                    (string) $room->public_id,
                    $version,
                    $items,
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /** @return array<string, mixed> */
    private function sanitize(array $placement): array
    {
        $artwork = (array) ($placement['artwork'] ?? []);

        return [
            'placement_id' => (string) ($placement['id'] ?? ''),
            'artwork_id' => (string) ($artwork['id'] ?? ''),
            'availability' => strtolower((string) ($artwork['availability'] ?? 'unavailable')),
            'sold' => (bool) ($artwork['sold'] ?? false),
            'interactive' => (bool) ($placement['interactive'] ?? false),
        ];
    }
}
