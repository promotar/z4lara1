<?php

namespace Modules\Store\Application\Coupons;

final readonly class CouponApplicationResult
{
    /**
     * @param  array<string, int>  $lineDiscounts
     * @param  list<array{variant_public_id: string, product_public_id: string, subtotal_minor: int, discount_minor: int}>  $eligibleItems
     */
    public function __construct(
        public int $promotionId,
        public string $promotionPublicId,
        public string $code,
        public string $discountType,
        public ?int $fixedAmountMinor,
        public ?int $percentageBasisPoints,
        public int $eligibleSubtotalMinor,
        public int $discountMinor,
        public array $lineDiscounts,
        public array $eligibleItems,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'promotion_id' => $this->promotionId,
            'promotion_public_id' => $this->promotionPublicId,
            'code' => $this->code,
            'discount_type' => $this->discountType,
            'fixed_amount_minor' => $this->fixedAmountMinor,
            'percentage_basis_points' => $this->percentageBasisPoints,
            'eligible_subtotal_minor' => $this->eligibleSubtotalMinor,
            'discount_minor' => $this->discountMinor,
            'line_discounts' => $this->lineDiscounts,
            'eligible_items' => $this->eligibleItems,
        ];
    }
}
