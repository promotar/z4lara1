<?php

namespace Modules\Store\Contracts\Readiness;

final readonly class StoreReadinessData
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public bool $available,
        public string $currency,
        public string $country,
        public string $defaultPaymentMethod,
        public array $capabilities,
    ) {}
}
