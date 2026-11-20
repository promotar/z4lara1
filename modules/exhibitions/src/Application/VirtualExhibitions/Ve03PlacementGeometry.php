<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

/**
 * Evaluates artwork dimensions against an authored wall segment.
 *
 * Slots remain stable interaction anchors in the 3D hall, while their usable
 * width is calculated from the wall edges and the artwork currently occupying
 * neighbouring anchors. This keeps old placement IDs stable without forcing
 * artists into an arbitrary 60–100 percent size range.
 */
final class Ve03PlacementGeometry
{
    public const MINIMUM_GAP_METERS = 0.20;

    /**
     * @param  iterable<int, array{slot: object, width_m?: float|int|null, height_m?: float|int|null, dimensions_known?: bool, kind?: string}>  $blockers
     * @return array{fits: bool, max_width_m: float, max_height_m: float, gap_m: float, reason: ?string, wall_id: ?string}
     */
    public function evaluate(object $candidate, float $width, float $height, iterable $blockers = []): array
    {
        $wall = $this->wall($candidate);
        $maximumHeight = $wall['maximum_height_m'] ?? max(0.01, (float) $candidate->max_height_m);
        $maximumWidth = max(0.01, (float) $candidate->max_width_m);
        $gap = $wall['minimum_gap_m'] ?? self::MINIMUM_GAP_METERS;
        $unknownDimensionsOnWall = false;

        if ($wall !== null) {
            $candidateCoordinate = $this->coordinate($candidate, $wall);
            $leftBoundary = -$wall['edge_allowance_m'];
            $rightBoundary = $wall['length_m'] + $wall['edge_allowance_m'];

            foreach ($blockers as $blocker) {
                $blockingSlot = $blocker['slot'] ?? null;
                if (! is_object($blockingSlot)) {
                    continue;
                }
                $blockingWall = $this->wall($blockingSlot);
                if ($blockingWall === null || ! hash_equals($wall['id'], $blockingWall['id'])) {
                    continue;
                }

                if (($blocker['dimensions_known'] ?? true) === false) {
                    $unknownDimensionsOnWall = true;
                    continue;
                }

                $blockingWidth = (float) ($blocker['width_m'] ?? 0);
                if ($blockingWidth <= 0) {
                    continue;
                }

                $blockingCoordinate = $this->coordinate($blockingSlot, $wall);
                if (abs($blockingCoordinate - $candidateCoordinate) < 0.0001) {
                    $maximumWidth = 0.0;
                    break;
                }
                if ($blockingCoordinate < $candidateCoordinate) {
                    $leftBoundary = max($leftBoundary, $blockingCoordinate + ($blockingWidth / 2) + $gap);
                } else {
                    $rightBoundary = min($rightBoundary, $blockingCoordinate - ($blockingWidth / 2) - $gap);
                }
            }

            $maximumWidth = max(0.0, 2 * min(
                $candidateCoordinate - $leftBoundary,
                $rightBoundary - $candidateCoordinate,
            ));
            if ($unknownDimensionsOnWall) {
                $maximumWidth = 0.0;
            }
        }

        $maximumWidth = round($maximumWidth, 3);
        $maximumHeight = round($maximumHeight, 3);
        $fitsWidth = $width > 0 && $width <= $maximumWidth + 0.0001;
        $fitsHeight = $height > 0 && $height <= $maximumHeight + 0.0001;
        $reason = null;

        if ($unknownDimensionsOnWall) {
            $reason = 'A neighbouring artwork has no physical dimensions. Complete its dimensions before placing another artwork on this wall.';
        } elseif (! $fitsWidth) {
            $reason = $maximumWidth > 0
                ? 'The wall space is too narrow here. This position safely supports artwork up to '.$this->centimeters($maximumWidth).' cm wide.'
                : 'This wall position is blocked by a neighbouring artwork or reservation.';
        } elseif (! $fitsHeight) {
            $reason = 'The artwork is too tall for the safe wall area. This position supports artwork up to '.$this->centimeters($maximumHeight).' cm high.';
        }

        return [
            'fits' => $fitsWidth && $fitsHeight,
            'max_width_m' => $maximumWidth,
            'max_height_m' => $maximumHeight,
            'gap_m' => round($gap, 3),
            'reason' => $reason,
            'wall_id' => $wall['id'] ?? null,
        ];
    }

    /**
     * @return array{id: string, start: array{0: float, 1: float, 2: float}, axis: array{0: float, 1: float, 2: float}, length_m: float, edge_allowance_m: float, minimum_gap_m: float, maximum_height_m: float}|null
     */
    private function wall(object $slot): ?array
    {
        $metadata = json_decode((string) ($slot->metadata ?? ''), true);
        $wall = is_array($metadata) && is_array($metadata['placement_wall'] ?? null)
            ? $metadata['placement_wall']
            : null;
        $start = is_array($wall['start'] ?? null) ? array_map('floatval', $wall['start']) : [];
        $end = is_array($wall['end'] ?? null) ? array_map('floatval', $wall['end']) : [];
        $id = trim((string) ($wall['id'] ?? ''));
        if ($id === '' || count($start) !== 3 || count($end) !== 3) {
            return null;
        }

        $delta = [$end[0] - $start[0], $end[1] - $start[1], $end[2] - $start[2]];
        $length = sqrt(($delta[0] ** 2) + ($delta[1] ** 2) + ($delta[2] ** 2));
        if ($length < 0.01) {
            return null;
        }

        return [
            'id' => $id,
            'start' => [$start[0], $start[1], $start[2]],
            'axis' => [$delta[0] / $length, $delta[1] / $length, $delta[2] / $length],
            'length_m' => $length,
            'edge_allowance_m' => max(0.0, (float) ($wall['edge_allowance_m'] ?? 0.8)),
            'minimum_gap_m' => max(self::MINIMUM_GAP_METERS, (float) ($wall['minimum_gap_m'] ?? self::MINIMUM_GAP_METERS)),
            'maximum_height_m' => max(0.01, (float) ($wall['maximum_height_m'] ?? $slot->max_height_m)),
        ];
    }

    /** @param array{start: array{0: float, 1: float, 2: float}, axis: array{0: float, 1: float, 2: float}} $wall */
    private function coordinate(object $slot, array $wall): float
    {
        $position = json_decode((string) ($slot->translation ?? ''), true);
        $position = is_array($position) ? array_map('floatval', $position) : [];
        if (count($position) !== 3) {
            return 0.0;
        }

        return (($position[0] - $wall['start'][0]) * $wall['axis'][0])
            + (($position[1] - $wall['start'][1]) * $wall['axis'][1])
            + (($position[2] - $wall['start'][2]) * $wall['axis'][2]);
    }

    private function centimeters(float $meters): int
    {
        return (int) floor(($meters * 100) + 0.0001);
    }
}
