<?php

namespace Modules\Store\Application\Coupons;

use RuntimeException;

final class CouponRejected extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $customerMessage,
    ) {
        parent::__construct($customerMessage);
    }
}
