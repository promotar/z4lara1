<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use Illuminate\Support\Facades\DB;

/**
 * Corrects the authored display layout of the five Art INPA luxury halls.
 *
 * The first hall packages concentrated every display in the secondary rooms.
 * Short walls consequently received more frames than they could hold, while
 * the arrival room had no display at all. The normalizer keeps stable slot
 * codes (and therefore existing placements) but assigns each slot to a more
 * spacious wall or to a deliberate freestanding display in an open atrium.
 */
final class Ve03LuxuryHallSlotLayout
{
    private const VERSION = 4;

    /** @var array<string, int> */
    private const CAPACITIES = [
        'ivory-pearl-20' => 20,
        'noir-gold-40' => 40,
        'champagne-aurora-60' => 60,
        'midnight-azure-80' => 80,
        'royal-travertine-100' => 100,
    ];

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function normalizeMetadata(array $metadata): array
    {
        $code = strtolower(trim((string) ($metadata['environment_code'] ?? '')));
        $capacity = self::CAPACITIES[$code] ?? null;
        $slots = array_values(is_array($metadata['slots'] ?? null) ? $metadata['slots'] : []);
        if ($capacity === null || count($slots) !== $capacity) {
            return $metadata;
        }

        $roomCodes = array_values(array_filter(array_map(
            static fn (mixed $room): string => is_array($room) ? (string) ($room['code'] ?? '') : '',
            is_array($metadata['rooms'] ?? null) ? $metadata['rooms'] : [],
        )));
        $placements = $this->placements($code);
        if (count($placements) !== $capacity) {
            return $metadata;
        }
        foreach ($placements as $placement) {
            if (! in_array($placement['room_code'], $roomCodes, true)) {
                return $metadata;
            }
        }

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)) {
                return $metadata;
            }
            $placement = $placements[$index];
            if (! isset($slot['_layout_original'])) {
                $slot['_layout_original'] = [
                    'room_code' => $slot['room_code'] ?? null,
                    'translation' => $slot['translation'] ?? null,
                    'rotation' => $slot['rotation'] ?? null,
                    'frame_profile' => $slot['frame_profile'] ?? null,
                    'source_anchor' => $slot['source_anchor'] ?? null,
                ];
            }
            $slot['room_code'] = $placement['room_code'];
            $slot['translation'] = $placement['translation'];
            $slot['rotation'] = $placement['rotation'];
            $slot['source_anchor'] = null;
            $slot['frame_profile'] = $placement['support'] === null
                ? 'dynamic-wall-frame'
                : 'freestanding-panel';
            $slot['display_support'] = $placement['support'];
            $slot['support_color'] = $placement['support_color'];
            $slot['placement_wall'] = $placement['placement_wall'];
            $slot['layout_version'] = self::VERSION;
            $slots[$index] = $slot;
        }

        $wallHeights = [];
        foreach ($slots as $slot) {
            $wallId = (string) data_get($slot, 'placement_wall.id', '');
            if ($wallId === '') {
                continue;
            }
            $wallHeights[$wallId] = max(
                (float) ($wallHeights[$wallId] ?? 0),
                (float) ($slot['max_height_m'] ?? 0),
            );
        }
        foreach ($slots as $index => $slot) {
            $wallId = (string) data_get($slot, 'placement_wall.id', '');
            if ($wallId === '' || ! isset($wallHeights[$wallId]) || $wallHeights[$wallId] <= 0) {
                continue;
            }
            $slot['placement_wall']['maximum_height_m'] = round($wallHeights[$wallId], 3);
            $slots[$index] = $slot;
        }

        $metadata['slots'] = $slots;
        $metadata['_slot_layout_version'] = self::VERSION;

        return $metadata;
    }

    /** Normalize every already-imported version without changing slot IDs. */
    public function normalizeImportedVersions(): int
    {
        $updated = 0;
        foreach (DB::table('exhibition_virtual_environment_versions')->orderBy('id')->get() as $version) {
            $metadata = json_decode((string) $version->metadata_report, true);
            if (! is_array($metadata)) {
                continue;
            }
            $normalized = $this->normalizeMetadata($metadata);
            if (($normalized['_slot_layout_version'] ?? null) !== self::VERSION
                || $normalized === $metadata) {
                continue;
            }

            DB::transaction(function () use ($version, $normalized): void {
                $rooms = DB::table('exhibition_virtual_environment_rooms')
                    ->where('version_id', $version->id)
                    ->pluck('id', 'room_code');
                foreach ($normalized['slots'] as $slot) {
                    $roomId = $rooms->get((string) $slot['room_code']);
                    if (! $roomId) {
                        continue;
                    }
                    DB::table('exhibition_virtual_environment_slots')
                        ->where('version_id', $version->id)
                        ->where('slot_code', $slot['slot_code'])
                        ->update([
                            'room_id' => $roomId,
                            'translation' => json_encode($slot['translation'], JSON_THROW_ON_ERROR),
                            'rotation' => json_encode($slot['rotation'], JSON_THROW_ON_ERROR),
                            'frame_profile' => $slot['frame_profile'],
                            'source_anchor' => null,
                            'metadata' => json_encode($slot, JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                }
                DB::table('exhibition_virtual_environment_versions')->where('id', $version->id)->update([
                    'metadata_report' => json_encode($normalized, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
                DB::table('exhibition_virtual_environments')->where('id', $version->environment_id)->increment('placement_version');
            });
            $updated++;
        }

        return $updated;
    }

    /** @return list<array{room_code:string,translation:list<float>,rotation:list<float>,support:?string,support_color:?string}> */
    private function placements(string $code): array
    {
        return match ($code) {
            'ivory-pearl-20' => $this->ivoryPearl(),
            'noir-gold-40' => $this->noirGold(),
            'champagne-aurora-60' => $this->champagneAurora(),
            'midnight-azure-80' => $this->midnightAzure(),
            'royal-travertine-100' => $this->royalTravertine(),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function ivoryPearl(): array
    {
        return [
            // The atrium contains a reception desk and a signature sculpture.
            // Spread the eight displays across all four walls. The portal-wall
            // positions sit on its solid side sections, while the end-wall and
            // side-wall positions remain clear of the desk, bench and sculpture.
            ...$this->line('atrium', [-5.35, 1.72, 0.16], [5.35, 1.72, 0.16], 0.0, 2),
            ...$this->line('atrium', [-4.0, 1.72, 9.84], [4.0, 1.72, 9.84], M_PI, 2),
            ...$this->line('atrium', [-8.84, 1.72, 3.0], [-8.84, 1.72, 7.0], M_PI_2, 2),
            ...$this->line('atrium', [8.84, 1.72, 3.0], [8.84, 1.72, 7.0], -M_PI_2, 2),

            // The gallery keeps twelve displays but now uses its entrance wall,
            // both long walls, and the solid end wall instead of leaving one wall empty.
            ...$this->line('gallery-01', [-8.84, 1.72, -2.2], [-8.84, 1.72, -11.8], M_PI_2, 4),
            ...$this->line('gallery-01', [8.84, 1.72, -2.2], [8.84, 1.72, -11.8], -M_PI_2, 4),
            ...$this->line('gallery-01', [-5.35, 1.72, -0.16], [5.35, 1.72, -0.16], M_PI, 2),
            ...$this->line('gallery-01', [-4.2, 1.72, -13.84], [4.2, 1.72, -13.84], 0.0, 2),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function noirGold(): array
    {
        $placements = [];
        foreach ([35, 55, 125, 145, 215, 235, 305, 325] as $degrees) {
            $angle = deg2rad($degrees);
            $placements[] = $this->point(
                'rotunda',
                [sin($angle) * 6.54, 1.72, cos($angle) * 6.54],
                $angle + M_PI,
            );
        }
        $specifications = [
            ['north-wing', [0.0, 13.0], 0.0],
            ['east-wing', [13.0, 0.0], M_PI_2],
            ['south-wing', [0.0, -13.0], M_PI],
            ['west-wing', [-13.0, 0.0], -M_PI_2],
        ];
        foreach ($specifications as [$room, $center, $yaw]) {
            $placements = [
                ...$placements,
                ...$this->roomLines($room, $center, 11.0, 12.0, $yaw, ['west' => 3, 'east' => 3, 'north' => 2]),
            ];
        }

        return $placements;
    }

    /** @return list<array<string, mixed>> */
    private function champagneAurora(): array
    {
        $placements = [
            ...$this->line('aurora-courtyard', [-7.05, 1.72, -6.2], [-7.05, 1.72, 11.8], M_PI_2, 6, 'freestanding-panel', '#f0e3cf'),
            ...$this->line('aurora-courtyard', [7.05, 1.72, -6.2], [7.05, 1.72, 11.8], -M_PI_2, 6, 'freestanding-panel', '#f0e3cf'),
        ];
        $placements = [
            ...$placements,
            ...$this->roomLines('west-promenade', [-13.5, 2.0], 10.0, 24.0, 0.0, ['west' => 6, 'north' => 5, 'south' => 5]),
            ...$this->roomLines('north-pavilion', [0.0, -13.0], 18.0, 10.0, 0.0, ['west' => 4, 'east' => 4, 'south' => 8]),
            ...$this->roomLines('east-promenade', [13.5, 2.0], 10.0, 24.0, 0.0, ['east' => 6, 'north' => 5, 'south' => 5]),
        ];

        return $placements;
    }

    /** @return list<array<string, mixed>> */
    private function midnightAzure(): array
    {
        $placements = [
            ...$this->line('azure-hub', [-2.6, 1.72, 6.65], [2.6, 1.72, 6.65], M_PI, 3, 'freestanding-panel', '#182b3b'),
            ...$this->line('azure-hub', [-2.6, 1.72, -6.65], [2.6, 1.72, -6.65], 0.0, 3, 'freestanding-panel', '#182b3b'),
            ...$this->line('azure-hub', [-6.65, 1.72, -2.6], [-6.65, 1.72, 2.6], M_PI_2, 3, 'freestanding-panel', '#182b3b'),
            ...$this->line('azure-hub', [6.65, 1.72, -2.6], [6.65, 1.72, 2.6], -M_PI_2, 3, 'freestanding-panel', '#182b3b'),
        ];
        $angles = [M_PI_4, 3 * M_PI_4, 5 * M_PI_4, 7 * M_PI_4];
        foreach ($angles as $index => $yaw) {
            $center = [sin($yaw) * 15.3, cos($yaw) * 15.3];
            $placements = [
                ...$placements,
                ...$this->roomLines('prism-wing-'.($index + 1), $center, 12.0, 16.0, $yaw, ['west' => 6, 'east' => 6, 'north' => 5]),
            ];
        }

        return $placements;
    }

    /** @return list<array<string, mixed>> */
    private function royalTravertine(): array
    {
        $placements = [
            ...$this->line('royal-court', [-6.2, 1.72, -6.5], [-6.2, 1.72, 18.0], M_PI_2, 8, 'freestanding-panel', '#cbb79e'),
            ...$this->line('royal-court', [6.2, 1.72, -6.5], [6.2, 1.72, 18.0], -M_PI_2, 7, 'freestanding-panel', '#cbb79e'),
        ];
        $specifications = [
            ['west-salon-1', [-16.0, 11.0], 11.0, 15.0, 0.0, ['west' => 7, 'north' => 5, 'south' => 5]],
            ['west-salon-2', [-16.0, -6.0], 11.0, 15.0, 0.0, ['west' => 7, 'north' => 5, 'south' => 5]],
            ['grand-nave', [0.0, -16.0], 22.0, 11.0, 0.0, ['west' => 5, 'east' => 5, 'south' => 7]],
            ['east-salon-2', [16.0, -6.0], 11.0, 15.0, 0.0, ['east' => 7, 'north' => 5, 'south' => 5]],
            ['east-salon-1', [16.0, 11.0], 11.0, 15.0, 0.0, ['east' => 7, 'north' => 5, 'south' => 5]],
        ];
        foreach ($specifications as [$room, $center, $width, $depth, $yaw, $counts]) {
            $placements = [...$placements, ...$this->roomLines($room, $center, $width, $depth, $yaw, $counts)];
        }

        return $placements;
    }

    /** @param array{0:float,1:float} $center
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private function roomLines(string $room, array $center, float $width, float $depth, float $yaw, array $counts): array
    {
        $lines = [];
        $margin = 1.3;
        $definitions = [
            'west' => [[-$width / 2 + .16, -$depth / 2 + $margin], [-$width / 2 + .16, $depth / 2 - $margin], M_PI_2],
            'east' => [[$width / 2 - .16, -$depth / 2 + $margin], [$width / 2 - .16, $depth / 2 - $margin], -M_PI_2],
            'north' => [[-$width / 2 + $margin, $depth / 2 - .16], [$width / 2 - $margin, $depth / 2 - .16], M_PI],
            'south' => [[-$width / 2 + $margin, -$depth / 2 + .16], [$width / 2 - $margin, -$depth / 2 + .16], 0.0],
        ];
        foreach ($definitions as $side => [$start, $end, $localYaw]) {
            $count = (int) ($counts[$side] ?? 0);
            if ($count < 1) {
                continue;
            }
            $worldStart = $this->localPoint($center, $yaw, $start);
            $worldEnd = $this->localPoint($center, $yaw, $end);
            $lines = [
                ...$lines,
                ...$this->line($room, [$worldStart[0], 1.72, $worldStart[1]], [$worldEnd[0], 1.72, $worldEnd[1]], $yaw + $localYaw, $count),
            ];
        }

        return $lines;
    }

    /** @param array{0:float,1:float} $center
     * @param  array{0:float,1:float}  $point
     * @return array{0:float,1:float}
     */
    private function localPoint(array $center, float $yaw, array $point): array
    {
        $cos = cos($yaw);
        $sin = sin($yaw);

        return [
            $center[0] + $point[0] * $cos + $point[1] * $sin,
            $center[1] - $point[0] * $sin + $point[1] * $cos,
        ];
    }

    /** @param array{0:float,1:float,2:float} $start
     * @param  array{0:float,1:float,2:float}  $end
     * @return list<array<string, mixed>>
     */
    private function line(string $room, array $start, array $end, float $yaw, int $count, ?string $support = null, ?string $supportColor = null): array
    {
        $points = [];
        $wall = [
            'id' => $room.'-'.substr(hash('sha256', json_encode([$start, $end, round($yaw, 8), $support], JSON_THROW_ON_ERROR)), 0, 12),
            'start' => array_map(static fn (float $value): float => round($value, 6), $start),
            'end' => array_map(static fn (float $value): float => round($value, 6), $end),
            'edge_allowance_m' => $count > 1 ? 0.8 : 0.0,
            'minimum_gap_m' => Ve03PlacementGeometry::MINIMUM_GAP_METERS,
            'capacity' => $count,
        ];
        for ($index = 0; $index < $count; $index++) {
            $ratio = $count === 1 ? .5 : $index / ($count - 1);
            $points[] = $this->point($room, [
                $start[0] + ($end[0] - $start[0]) * $ratio,
                $start[1] + ($end[1] - $start[1]) * $ratio,
                $start[2] + ($end[2] - $start[2]) * $ratio,
            ], $yaw, $support, $supportColor, $wall);
        }

        return $points;
    }

    /** @param array{0:float,1:float,2:float} $translation
     * @param  array<string, mixed>|null  $placementWall
     * @return array{room_code:string,translation:list<float>,rotation:list<float>,support:?string,support_color:?string}
     */
    private function point(string $room, array $translation, float $yaw, ?string $support = null, ?string $supportColor = null, ?array $placementWall = null): array
    {
        return [
            'room_code' => $room,
            'translation' => array_map(static fn (float $value): float => round($value, 6), $translation),
            'rotation' => [0.0, round(sin($yaw / 2), 8), 0.0, round(cos($yaw / 2), 8)],
            'support' => $support,
            'support_color' => $supportColor,
            'placement_wall' => $placementWall,
        ];
    }
}
