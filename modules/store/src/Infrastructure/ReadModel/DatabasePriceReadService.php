<?php

namespace Modules\Store\Infrastructure\ReadModel;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Store\Contracts\Pricing\PriceReadService;
use Modules\Store\Contracts\Pricing\PriceView;
use Modules\Store\Domain\Pricing\PriceResolver;
use Modules\Store\Domain\Pricing\PricingRepository;
use Modules\Store\Domain\Shared\Exceptions\PriceConflict;

final readonly class DatabasePriceReadService implements PriceReadService
{
    public function __construct(private PricingRepository $prices, private PriceResolver $resolver) {}

    public function currentForVariant(string $variantPublicId, string $currency, DateTimeImmutable $at): ?PriceView
    {
        $row = DB::table('store_product_variants')->where('public_id', $variantPublicId)->first(['id', 'public_id']);
        if ($row === null) {
            return null;
        }

        try {
            $resolved = $this->resolver->resolve(
                $this->prices->recordsForVariant((int) $row->id, strtoupper($currency)),
                strtoupper($currency),
                $at,
            );
        } catch (PriceConflict) {
            return null;
        }

        return new PriceView(
            variantPublicId: (string) $row->public_id,
            regularAmountMinor: $resolved->regular->amountMinor,
            saleAmountMinor: $resolved->sale?->amountMinor,
            effectiveAmountMinor: $resolved->effective->amountMinor,
            currency: $resolved->effective->currency,
            priceRecordPublicId: $resolved->priceRecordPublicId,
            resolvedAt: $resolved->resolvedAt,
        );
    }
}
