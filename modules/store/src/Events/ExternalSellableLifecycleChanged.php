<?php

namespace Modules\Store\Events;

use Modules\Store\Contracts\Integrations\V1\ExternalSellableLifecycleRecord;

final readonly class ExternalSellableLifecycleChanged
{
    public int $sequence;

    public string $ownerCode;

    public string $externalId;

    public string $action;

    public int $quantity;

    public string $orderPublicId;

    public string $orderNumber;

    public string $orderItemPublicId;

    public ?int $customerUserId;

    public string $currency;

    public int $unitPriceMinor;

    public int $discountMinor;

    public int $lineTotalMinor;

    public string $paymentStatus;

    public string $idempotencyKey;

    public function __construct(ExternalSellableLifecycleRecord $record)
    {
        $this->sequence = $record->sequence;
        $this->ownerCode = $record->ownerCode;
        $this->externalId = $record->externalId;
        $this->action = $record->action;
        $this->quantity = $record->quantity;
        $this->orderPublicId = $record->orderPublicId;
        $this->orderNumber = $record->orderNumber;
        $this->orderItemPublicId = $record->orderItemPublicId;
        $this->customerUserId = $record->customerUserId;
        $this->currency = $record->currency;
        $this->unitPriceMinor = $record->unitPriceMinor;
        $this->discountMinor = $record->discountMinor;
        $this->lineTotalMinor = $record->lineTotalMinor;
        $this->paymentStatus = $record->paymentStatus;
        $this->idempotencyKey = $record->idempotencyKey;
    }
}
