<?php

namespace Modules\Store\Application\Coupons;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Modules\Store\Domain\Shared\PublicIdGenerator;

final readonly class CouponApplicationService
{
    public function __construct(
        private ConnectionInterface $database,
        private PublicIdGenerator $ids,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function evaluate(
        string $code,
        Collection $items,
        ?int $userId = null,
        ?string $email = null,
        ?string $phone = null,
        bool $lock = false,
        bool $enforceIdentity = false,
    ): CouponApplicationResult {
        $normalized = $this->normalize($code);
        if ($normalized === '') {
            throw new CouponRejected('not_found', 'Coupon does not exist.');
        }

        $query = $this->database->table('store_promotions as promotion')
            ->join('store_promotion_actions as action', 'action.promotion_id', '=', 'promotion.id')
            ->where('promotion.mode', 'code')
            ->where('promotion.code_normalized', $normalized);
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first([
            'promotion.id',
            'promotion.public_id',
            'promotion.code_normalized',
            'promotion.status',
            'promotion.starts_at',
            'promotion.ends_at',
            'promotion.usage_limit',
            'promotion.per_customer_limit',
            'promotion.minimum_subtotal_minor',
            'action.action_type',
            'action.amount_minor',
            'action.percentage_basis_points',
            'action.maximum_discount_minor',
        ]);

        if ($coupon === null) {
            throw new CouponRejected('not_found', 'Coupon does not exist.');
        }
        if ((string) $coupon->status !== 'active') {
            throw new CouponRejected('inactive', 'Coupon is not active.');
        }

        $now = now();
        if ($coupon->starts_at !== null && $now->lt($coupon->starts_at)) {
            throw new CouponRejected('not_started', 'Coupon has not started yet.');
        }
        if ($coupon->ends_at !== null && $now->gte($coupon->ends_at)) {
            throw new CouponRejected('expired', 'Coupon has expired.');
        }

        $eligible = $this->eligibleItems((int) $coupon->id, $items);
        if ($eligible->isEmpty()) {
            throw new CouponRejected('ineligible', 'Coupon is not valid for the products in your cart.');
        }
        $eligibleSubtotal = (int) $eligible->sum('line_total_minor');
        if ($eligibleSubtotal < (int) $coupon->minimum_subtotal_minor) {
            throw new CouponRejected('minimum_not_met', 'Your eligible products have not reached the coupon minimum.');
        }

        $this->assertUsageAvailable(
            (int) $coupon->id,
            $coupon->usage_limit === null ? null : (int) $coupon->usage_limit,
            $coupon->per_customer_limit === null ? null : (int) $coupon->per_customer_limit,
            $userId,
            $email,
            $phone,
            $enforceIdentity,
        );

        $discountType = (string) $coupon->action_type;
        $fixed = $coupon->amount_minor === null ? null : (int) $coupon->amount_minor;
        $percentage = $coupon->percentage_basis_points === null ? null : (int) $coupon->percentage_basis_points;
        $discount = match ($discountType) {
            'percentage_discount' => intdiv($eligibleSubtotal * max(0, (int) $percentage), 10_000),
            'fixed_amount_discount' => min($eligibleSubtotal, max(0, (int) $fixed)),
            default => throw new CouponRejected('inactive', 'Coupon has an unsupported discount rule.'),
        };
        if ($coupon->maximum_discount_minor !== null) {
            $discount = min($discount, (int) $coupon->maximum_discount_minor);
        }
        $discount = min($eligibleSubtotal, max(0, $discount));
        if ($discount === 0) {
            throw new CouponRejected('ineligible', 'Coupon does not produce a discount for these products.');
        }

        $lineDiscounts = $this->allocate($eligible, $eligibleSubtotal, $discount);
        $eligibleItems = $eligible->map(fn (array $item): array => [
            'variant_public_id' => (string) $item['variant_public_id'],
            'product_public_id' => (string) $item['product_public_id'],
            'subtotal_minor' => (int) $item['line_total_minor'],
            'discount_minor' => (int) ($lineDiscounts[$item['variant_public_id']] ?? 0),
        ])->values()->all();

        return new CouponApplicationResult(
            (int) $coupon->id,
            (string) $coupon->public_id,
            (string) $coupon->code_normalized,
            $discountType,
            $fixed,
            $percentage,
            $eligibleSubtotal,
            $discount,
            $lineDiscounts,
            $eligibleItems,
        );
    }

    public function recordUsage(
        CouponApplicationResult $coupon,
        int $orderId,
        ?int $userId,
        ?string $email,
        ?string $phone,
    ): void {
        $this->database->table('store_coupon_usages')->insert([
            'public_id' => (string) $this->ids->generate(),
            'promotion_id' => $coupon->promotionId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'guest_identity_hash' => $userId === null ? $this->identityHash($email, $phone) : null,
            'status' => 'counted',
            'counted_at' => now(),
            'released_at' => null,
        ]);
    }

    public function releaseUsageForOrder(int $orderId): void
    {
        $this->database->table('store_coupon_usages')
            ->where('order_id', $orderId)
            ->where('status', 'counted')
            ->update(['status' => 'released', 'released_at' => now()]);
    }

    public function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function eligibleItems(int $promotionId, Collection $items): Collection
    {
        $targets = $this->database->table('store_promotion_targets')
            ->where('promotion_id', $promotionId)
            ->get(['target_type', 'target_public_id']);
        if ($targets->contains('target_type', 'all_catalog')) {
            return $items->values();
        }

        $products = $targets->where('target_type', 'product')->pluck('target_public_id')->filter()->all();
        $variants = $targets->where('target_type', 'variant')->pluck('target_public_id')->filter()->all();
        $categories = $targets->where('target_type', 'category')->pluck('target_public_id')->filter()->all();
        $categoryProducts = $categories === [] ? [] : $this->database->table('store_product_categories as link')
            ->join('store_products as product', 'product.id', '=', 'link.product_id')
            ->join('store_categories as category', 'category.id', '=', 'link.category_id')
            ->whereIn('category.public_id', $categories)
            ->pluck('product.public_id')
            ->all();

        return $items->filter(fn (array $item): bool => in_array($item['product_public_id'], $products, true)
            || in_array($item['variant_public_id'], $variants, true)
            || in_array($item['product_public_id'], $categoryProducts, true)
        )->values();
    }

    private function assertUsageAvailable(
        int $promotionId,
        ?int $usageLimit,
        ?int $perCustomerLimit,
        ?int $userId,
        ?string $email,
        ?string $phone,
        bool $enforceIdentity,
    ): void {
        if ($usageLimit !== null && $this->database->table('store_coupon_usages')
            ->where('promotion_id', $promotionId)
            ->where('status', 'counted')
            ->count() >= $usageLimit) {
            throw new CouponRejected('total_limit', 'Coupon has reached its total usage limit.');
        }
        if ($perCustomerLimit === null) {
            return;
        }

        $query = $this->database->table('store_coupon_usages')
            ->where('promotion_id', $promotionId)
            ->where('status', 'counted');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $identity = $this->identityHash($email, $phone);
            if ($identity === null) {
                if ($enforceIdentity) {
                    throw new CouponRejected('identity_required', 'Sign in or provide your email and phone to use this coupon.');
                }

                return;
            }
            $query->where('guest_identity_hash', $identity);
        }
        if ($query->count() >= $perCustomerLimit) {
            throw new CouponRejected('customer_limit', 'You have used this coupon the maximum number of times.');
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function allocate(Collection $items, int $subtotal, int $discount): array
    {
        $remaining = $discount;
        $last = $items->count() - 1;
        $allocated = [];
        foreach ($items->values() as $index => $item) {
            $line = $index === $last
                ? $remaining
                : min((int) $item['line_total_minor'], intdiv($discount * (int) $item['line_total_minor'], $subtotal));
            $line = min($remaining, max(0, $line));
            $allocated[(string) $item['variant_public_id']] = $line;
            $remaining -= $line;
        }

        return $allocated;
    }

    private function identityHash(?string $email, ?string $phone): ?string
    {
        $email = strtolower(trim((string) $email));
        $phone = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($email !== '') {
            return hash('sha256', 'email:'.$email);
        }

        return $phone === '' ? null : hash('sha256', 'phone:'.$phone);
    }
}
