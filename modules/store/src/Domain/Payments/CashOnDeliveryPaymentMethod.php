<?php

namespace Modules\Store\Domain\Payments;

use Modules\Store\Contracts\Payments\PaymentMethod;
use Modules\Store\Support\StoreModule;

final class CashOnDeliveryPaymentMethod implements PaymentMethod
{
    public function __construct(
        private readonly bool $enabled = true,
        private readonly string $configuredLabel = 'Cash on Delivery',
    ) {}

    public function code(): string
    {
        return StoreModule::DEFAULT_PAYMENT_METHOD;
    }

    public function label(): string
    {
        return $this->configuredLabel;
    }

    public function isAvailable(): bool
    {
        return $this->enabled;
    }
}
