<?php

namespace Modules\Store\Events;

final readonly class OrderStatusChanged
{
    public function __construct(
        public string $orderPublicId,
        public string $orderNumber,
        public string $status,
        public ?string $customerEmail,
    ) {}
}
