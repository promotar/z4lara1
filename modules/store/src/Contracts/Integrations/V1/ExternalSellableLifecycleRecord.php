<?php

namespace Modules\Store\Contracts\Integrations\V1;

final readonly class ExternalSellableLifecycleRecord
{
    public function __construct(
        public int $sequence,
        public string $ownerCode,
        public string $externalId,
        public string $action,
        public int $quantity,
        public string $orderPublicId,
        public string $orderNumber,
        public string $orderItemPublicId,
        public ?int $customerUserId,
        public string $currency,
        public int $unitPriceMinor,
        public int $discountMinor,
        public int $lineTotalMinor,
        public string $paymentStatus,
        public string $idempotencyKey,
    ) {}
}
