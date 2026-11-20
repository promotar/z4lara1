<?php

namespace Modules\Store\Domain\Promotions;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\Money;

final class PromotionResolver
{
    /** @param list<PromotionCandidate> $candidates */
    public function resolve(Money $base, array $candidates, DateTimeImmutable $at): PromotionResolution
    {
        $lines = [];
        foreach ($candidates as $candidate) {
            if (! $candidate->eligible) {
                continue;
            }
            $lines[] = new DiscountLine(
                $candidate->publicId,
                $candidate->discount($base),
                $candidate->priority,
                $candidate->stackingPolicy,
            );
        }

        $exclusive = array_values(array_filter($lines, static fn (DiscountLine $line): bool => $line->stackingPolicy === PromotionStackingPolicy::Exclusive));

        if ($exclusive !== []) {
            $selected = [$this->best($exclusive)];
        } else {
            $stackable = array_values(array_filter($lines, static fn (DiscountLine $line): bool => $line->stackingPolicy === PromotionStackingPolicy::Stackable));
            $bestPrice = array_values(array_filter($lines, static fn (DiscountLine $line): bool => $line->stackingPolicy === PromotionStackingPolicy::BestPrice));

            $stackableTotal = array_sum(array_map(static fn (DiscountLine $line): int => $line->discount->amountMinor, $stackable));
            $best = $bestPrice === [] ? null : $this->best($bestPrice);

            if ($best !== null && $best->discount->amountMinor > min($base->amountMinor, $stackableTotal)) {
                $selected = [$best];
            } else {
                usort($stackable, $this->compare(...));
                $selected = $stackable;
            }
        }

        $discountMinor = min(
            $base->amountMinor,
            array_sum(array_map(static fn (DiscountLine $line): int => $line->discount->amountMinor, $selected)),
        );
        $discount = new Money($discountMinor, $base->currency);

        return new PromotionResolution($base, $selected, $discount, $base->subtract($discount), $at);
    }

    /** @param list<DiscountLine> $lines */
    private function best(array $lines): DiscountLine
    {
        usort($lines, $this->compare(...));

        return $lines[0];
    }

    private function compare(DiscountLine $left, DiscountLine $right): int
    {
        return ($right->discount->amountMinor <=> $left->discount->amountMinor)
            ?: ($right->priority <=> $left->priority)
            ?: strcmp($left->promotionPublicId, $right->promotionPublicId);
    }
}
