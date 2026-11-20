<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

final class Ve03VersionComparator
{
    /**
     * @param  array<string, array<string, mixed>>  $currentSlots
     * @param  array<string, array<string, mixed>>  $candidateSlots
     * @param  list<string>  $occupiedSlotCodes
     * @return array<string, mixed>
     */
    public function compare(array $currentSlots, array $candidateSlots, array $occupiedSlotCodes = []): array
    {
        $added = array_values(array_diff(array_keys($candidateSlots), array_keys($currentSlots)));
        $removed = array_values(array_diff(array_keys($currentSlots), array_keys($candidateSlots)));
        $moved = [];
        $resized = [];
        $blockers = [];

        foreach (array_intersect(array_keys($currentSlots), array_keys($candidateSlots)) as $code) {
            $current = $currentSlots[$code];
            $candidate = $candidateSlots[$code];
            if ($this->changed($current['translation'] ?? [], $candidate['translation'] ?? [], 0.001)
                || $this->changed($current['rotation'] ?? [], $candidate['rotation'] ?? [], 0.0001)) {
                $moved[] = $code;
            }
            if (abs((float) ($current['max_width_m'] ?? 0) - (float) ($candidate['max_width_m'] ?? 0)) > 0.001
                || abs((float) ($current['max_height_m'] ?? 0) - (float) ($candidate['max_height_m'] ?? 0)) > 0.001) {
                $resized[] = $code;
            }
        }

        foreach ($occupiedSlotCodes as $code) {
            if (in_array($code, $removed, true)) {
                $blockers[] = ['slot_code' => $code, 'reason' => 'occupied_slot_removed'];

                continue;
            }
            if (! isset($currentSlots[$code], $candidateSlots[$code])) {
                continue;
            }
            if ((float) $candidateSlots[$code]['max_width_m'] + 0.001 < (float) $currentSlots[$code]['max_width_m']
                || (float) $candidateSlots[$code]['max_height_m'] + 0.001 < (float) $currentSlots[$code]['max_height_m']) {
                $blockers[] = ['slot_code' => $code, 'reason' => 'occupied_slot_reduced'];
            }
        }

        sort($added);
        sort($removed);
        sort($moved);
        sort($resized);

        return [
            'added_slots' => $added,
            'removed_slots' => $removed,
            'moved_slots' => $moved,
            'resized_slots' => $resized,
            'occupied_slot_codes' => array_values(array_unique($occupiedSlotCodes)),
            'blockers' => $blockers,
            'publishable' => $blockers === [],
        ];
    }

    /** @param array<int, mixed> $left @param array<int, mixed> $right */
    private function changed(array $left, array $right, float $epsilon): bool
    {
        if (count($left) !== count($right)) {
            return true;
        }
        foreach ($left as $index => $value) {
            if (abs((float) $value - (float) $right[$index]) > $epsilon) {
                return true;
            }
        }

        return false;
    }
}
