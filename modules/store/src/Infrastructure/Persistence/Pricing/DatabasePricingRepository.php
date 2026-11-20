<?php

namespace Modules\Store\Infrastructure\Persistence\Pricing;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Pricing\PriceRecord;
use Modules\Store\Domain\Pricing\PriceType;
use Modules\Store\Domain\Pricing\PricingRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Money;

final class DatabasePricingRepository implements PricingRepository
{
    public function variantIdByPublicId(string $variantPublicId, bool $forUpdate = false): ?int
    {
        $query = DB::table('store_product_variants')->where('public_id', $variantPublicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $value = $query->value('id');

        return $value === null ? null : (int) $value;
    }

    public function archiveCurrentRegular(int $variantId, string $currency, DateTimeImmutable $at): void
    {
        DB::table('store_prices')
            ->where('variant_id', $variantId)
            ->where('currency', $currency)
            ->where('type', PriceType::Regular->value)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->update(['status' => 'archived', 'archived_at' => $at, 'updated_at' => $at]);
    }

    public function insert(
        string $publicId,
        int $variantId,
        PriceType $type,
        Money $money,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        int $priority,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): void {
        DB::table('store_prices')->insert([
            'public_id' => $publicId,
            'variant_id' => $variantId,
            'type' => $type->value,
            'amount_minor' => $money->amountMinor,
            'currency' => $money->currency,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'priority' => $priority,
            'status' => 'active',
            'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function hasOverlappingSale(
        int $variantId,
        string $currency,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        int $priority,
        ?string $exceptPublicId = null,
    ): bool {
        return DB::table('store_prices')
            ->where('variant_id', $variantId)
            ->where('currency', $currency)
            ->where('type', PriceType::Sale->value)
            ->where('status', 'active')
            ->where('priority', $priority)
            ->when($exceptPublicId !== null, fn ($query) => $query->where('public_id', '!=', $exceptPublicId))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))
            ->when($endsAt !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<', $endsAt)))
            ->exists();
    }

    public function archiveSale(string $publicId, int $variantId, string $currency, DateTimeImmutable $at): bool
    {
        return DB::table('store_prices')
            ->where('public_id', $publicId)
            ->where('variant_id', $variantId)
            ->where('currency', $currency)
            ->where('type', PriceType::Sale->value)
            ->where('status', 'active')
            ->update(['status' => 'archived', 'archived_at' => $at, 'updated_at' => $at]) === 1;
    }

    public function recordsForVariant(int $variantId, string $currency): array
    {
        return DB::table('store_prices')
            ->where('variant_id', $variantId)
            ->where('currency', $currency)
            ->get()
            ->map(static fn (object $row): PriceRecord => new PriceRecord(
                publicId: (string) $row->public_id,
                type: PriceType::from((string) $row->type),
                money: new Money((int) $row->amount_minor, (string) $row->currency),
                startsAt: $row->starts_at === null ? null : new DateTimeImmutable((string) $row->starts_at),
                endsAt: $row->ends_at === null ? null : new DateTimeImmutable((string) $row->ends_at),
                priority: (int) $row->priority,
                status: (string) $row->status,
            ))
            ->all();
    }
}
