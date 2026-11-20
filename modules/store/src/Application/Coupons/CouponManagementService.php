<?php

namespace Modules\Store\Application\Coupons;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Store\Application\Promotions\PromotionTargetValidator;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\PublicId;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class CouponManagementService
{
    public function __construct(
        private ConnectionInterface $database,
        private TransactionManager $transactions,
        private PublicIdGenerator $ids,
        private PromotionTargetValidator $targets,
        private CouponApplicationService $applications,
    ) {}

    public function create(CouponDefinition $definition): string
    {
        $data = $this->validated($definition);
        $publicId = (string) $this->ids->generate();

        $this->transactions->run(function () use ($definition, $data, $publicId): void {
            $this->assertUniqueCode($data['code']);
            $now = now();
            $id = (int) $this->database->table('store_promotions')->insertGetId([
                'public_id' => $publicId,
                'name' => $data['name'],
                'code' => $data['code'],
                'code_normalized' => $data['code'],
                'mode' => 'code',
                'status' => $definition->active ? 'active' : 'draft',
                'priority' => 0,
                'stacking_policy' => 'exclusive',
                'starts_at' => $definition->startsAt,
                'ends_at' => $definition->endsAt,
                'usage_limit' => $definition->usageLimit,
                'per_customer_limit' => $definition->perCustomerLimit,
                'minimum_subtotal_minor' => $definition->minimumSubtotalMinor,
                'created_by' => $definition->actor->type === 'user' ? (int) $definition->actor->id : null,
                'updated_by' => $definition->actor->type === 'user' ? (int) $definition->actor->id : null,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'activated_at' => $definition->active ? $now : null,
                'archived_at' => null,
            ]);
            $this->replaceRules($id, $data, $now);
        });

        return $publicId;
    }

    public function update(string $publicId, int $expectedVersion, CouponDefinition $definition): void
    {
        $data = $this->validated($definition);
        $this->transactions->run(function () use ($publicId, $expectedVersion, $definition, $data): void {
            $coupon = $this->database->table('store_promotions')
                ->where('public_id', $publicId)
                ->where('mode', 'code')
                ->lockForUpdate()
                ->first();
            if ($coupon === null) {
                throw new InvalidArgumentException('Coupon does not exist.');
            }
            if ((int) $coupon->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Coupon version is stale.');
            }
            $this->assertUniqueCode($data['code'], (int) $coupon->id);
            $now = now();
            $updated = $this->database->table('store_promotions')
                ->where('id', $coupon->id)
                ->where('version', $expectedVersion)
                ->update([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'code_normalized' => $data['code'],
                    'status' => $definition->active ? 'active' : 'paused',
                    'starts_at' => $definition->startsAt,
                    'ends_at' => $definition->endsAt,
                    'usage_limit' => $definition->usageLimit,
                    'per_customer_limit' => $definition->perCustomerLimit,
                    'minimum_subtotal_minor' => $definition->minimumSubtotalMinor,
                    'updated_by' => $definition->actor->type === 'user' ? (int) $definition->actor->id : null,
                    'version' => $this->database->raw('version + 1'),
                    'updated_at' => $now,
                    'activated_at' => $definition->active ? ($coupon->activated_at ?? $now) : $coupon->activated_at,
                ]);
            if ($updated !== 1) {
                throw new ConcurrencyConflict('Coupon update lost a concurrency race.');
            }
            $this->replaceRules((int) $coupon->id, $data, $now);
        });
    }

    public function setActive(string $publicId, int $expectedVersion, bool $active, int $userId): void
    {
        $updated = $this->database->table('store_promotions')
            ->where('public_id', $publicId)
            ->where('mode', 'code')
            ->where('version', $expectedVersion)
            ->update([
                'status' => $active ? 'active' : 'paused',
                'activated_at' => $active ? now() : $this->database->raw('activated_at'),
                'updated_by' => $userId,
                'version' => $this->database->raw('version + 1'),
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new ConcurrencyConflict('Coupon status changed before your request was saved.');
        }
    }

    public function delete(string $publicId, string $confirmationCode): void
    {
        $this->transactions->run(function () use ($publicId, $confirmationCode): void {
            $coupon = $this->database->table('store_promotions')
                ->where('public_id', $publicId)
                ->where('mode', 'code')
                ->lockForUpdate()
                ->first(['id', 'code_normalized']);

            if ($coupon === null) {
                throw new InvalidArgumentException('Coupon does not exist.');
            }

            if ($this->applications->normalize($confirmationCode) !== (string) $coupon->code_normalized) {
                throw new InvalidArgumentException('Enter the coupon code exactly to confirm permanent deletion.');
            }

            if ($this->database->table('store_coupon_usages')->where('promotion_id', $coupon->id)->exists()) {
                throw new InvalidArgumentException('This coupon has order history and cannot be deleted. Disable it instead.');
            }

            $this->database->table('store_promotions')->where('id', $coupon->id)->delete();
        });
    }

    /** @return array{name: string, code: string, discount_type: string, fixed_amount_minor: ?int, percentage_basis_points: ?int, targets: list<array{type: string, public_id: ?string}>} */
    private function validated(CouponDefinition $definition): array
    {
        $name = trim($definition->name);
        $code = $this->applications->normalize($definition->code);
        if ($name === '') {
            throw new InvalidArgumentException('Coupon name is required.');
        }
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/', $code)) {
            throw new InvalidArgumentException('Coupon code must be 3–64 letters, numbers, dashes, or underscores without spaces.');
        }
        if ($definition->startsAt !== null && $definition->endsAt !== null && $definition->startsAt >= $definition->endsAt) {
            throw new InvalidArgumentException('Coupon end must be after its start.');
        }
        if ($definition->minimumSubtotalMinor < 0
            || ($definition->usageLimit !== null && $definition->usageLimit < 1)
            || ($definition->perCustomerLimit !== null && $definition->perCustomerLimit < 1)) {
            throw new InvalidArgumentException('Coupon limits cannot be negative or zero.');
        }
        if ($definition->discountType === 'percentage_discount') {
            if ($definition->percentageBasisPoints === null
                || $definition->percentageBasisPoints < 1
                || $definition->percentageBasisPoints > 10_000) {
                throw new InvalidArgumentException('Percentage discount must be greater than 0 and no more than 100%.');
            }
        } elseif ($definition->discountType === 'fixed_amount_discount') {
            if ($definition->fixedAmountMinor === null || $definition->fixedAmountMinor < 1) {
                throw new InvalidArgumentException('Fixed discount must be greater than zero.');
            }
        } else {
            throw new InvalidArgumentException('Coupon discount type is invalid.');
        }

        if ($definition->targets === []) {
            throw new InvalidArgumentException('Coupon requires at least one eligible target.');
        }
        $targets = [];
        $seen = [];
        foreach ($definition->targets as $target) {
            $type = (string) ($target['type'] ?? '');
            $publicId = $target['public_id'] ?? null;
            if (! in_array($type, ['all_catalog', 'product', 'category'], true)) {
                throw new InvalidArgumentException('Coupon product eligibility is invalid.');
            }
            if ($type === 'all_catalog') {
                $publicId = null;
            } else {
                $publicId = (string) new PublicId((string) $publicId);
                $this->targets->assertExists($type, $publicId);
            }
            $key = $type.':'.($publicId ?? '*');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $targets[] = ['type' => $type, 'public_id' => $publicId];
        }

        return [
            'name' => $name,
            'code' => $code,
            'discount_type' => $definition->discountType,
            'fixed_amount_minor' => $definition->discountType === 'fixed_amount_discount' ? $definition->fixedAmountMinor : null,
            'percentage_basis_points' => $definition->discountType === 'percentage_discount' ? $definition->percentageBasisPoints : null,
            'targets' => $targets,
        ];
    }

    private function assertUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = $this->database->table('store_promotions')->where('code_normalized', $code);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('Coupon code already exists.');
        }
    }

    /** @param array<string, mixed> $data */
    private function replaceRules(int $promotionId, array $data, mixed $now): void
    {
        $this->database->table('store_promotion_targets')->where('promotion_id', $promotionId)->delete();
        $this->database->table('store_promotion_actions')->where('promotion_id', $promotionId)->delete();

        $this->database->table('store_promotion_targets')->insert(array_map(
            static fn (array $target): array => [
                'promotion_id' => $promotionId,
                'target_type' => $target['type'],
                'target_public_id' => $target['public_id'],
                'target_key' => $target['type'].':'.($target['public_id'] ?? '*'),
                'include_descendants' => false,
                'created_at' => $now,
            ],
            $data['targets'],
        ));
        $this->database->table('store_promotion_actions')->insert([
            'promotion_id' => $promotionId,
            'action_type' => $data['discount_type'],
            'amount_minor' => $data['fixed_amount_minor'],
            'percentage_basis_points' => $data['percentage_basis_points'],
            'currency' => $data['fixed_amount_minor'] === null ? null : strtoupper((string) config('store.default_currency', 'JOD')),
            'maximum_discount_minor' => null,
            'schema_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
