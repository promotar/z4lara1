<?php

namespace Modules\Store\Domain\Pricing;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\Exceptions\PriceConflict;

final class PriceResolver
{
    /** @param list<PriceRecord> $records */
    public function resolve(array $records, string $currency, DateTimeImmutable $at): ResolvedPrice
    {
        $applicable = array_values(array_filter($records, static fn (PriceRecord $record): bool => $record->money->currency === $currency && $record->appliesAt($at)));

        $regular = $this->winner($applicable, PriceType::Regular);
        if ($regular === null) {
            throw new PriceConflict('No current regular price is configured.');
        }

        $sale = $this->winner($applicable, PriceType::Sale);
        if ($sale !== null && $sale->money->amountMinor > $regular->money->amountMinor) {
            throw new PriceConflict('Sale price cannot exceed the applicable regular price.');
        }

        $effective = $sale ?? $regular;

        return new ResolvedPrice(
            regular: $regular->money,
            sale: $sale?->money,
            effective: $effective->money,
            priceRecordPublicId: $effective->publicId,
            resolvedAt: $at,
        );
    }

    /** @param list<PriceRecord> $records */
    private function winner(array $records, PriceType $type): ?PriceRecord
    {
        $typed = array_values(array_filter($records, static fn (PriceRecord $record): bool => $record->type === $type));
        if ($typed === []) {
            return null;
        }

        usort($typed, static fn (PriceRecord $left, PriceRecord $right): int => $right->priority <=> $left->priority);

        if (isset($typed[1]) && $typed[0]->priority === $typed[1]->priority) {
            throw new PriceConflict("Ambiguous {$type->value} price precedence.");
        }

        return $typed[0];
    }
}
