<?php

namespace Modules\Store\Application\Readiness;

use Modules\Store\Contracts\Capabilities\StoreCapabilities;
use Modules\Store\Contracts\Payments\PaymentMethodRegistry;
use Modules\Store\Contracts\Readiness\StoreReadiness;
use Modules\Store\Contracts\Readiness\StoreReadinessData;

final class DefaultStoreReadiness implements StoreReadiness
{
    public function __construct(
        private readonly StoreCapabilities $capabilities,
        private readonly PaymentMethodRegistry $paymentMethods,
    ) {}

    public function status(): StoreReadinessData
    {
        return new StoreReadinessData(
            available: true,
            currency: (string) config('store.default_currency', 'JOD'),
            country: (string) config('store.default_country', 'JO'),
            defaultPaymentMethod: $this->paymentMethods->default()->code(),
            capabilities: $this->capabilities->all(),
        );
    }
}
