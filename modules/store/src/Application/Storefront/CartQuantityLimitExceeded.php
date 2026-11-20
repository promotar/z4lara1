<?php

namespace Modules\Store\Application\Storefront;

use RuntimeException;

final class CartQuantityLimitExceeded extends RuntimeException
{
    public function __construct(
        public readonly int $cartQuantity,
        public readonly int $requestedTotal,
        public readonly int $maximumQuantity,
    ) {
        parent::__construct('The requested total quantity exceeds current availability.');
    }
}
