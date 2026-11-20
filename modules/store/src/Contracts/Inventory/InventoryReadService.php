<?php

namespace Modules\Store\Contracts\Inventory;

use DateTimeImmutable;

interface InventoryReadService
{
    public function forVariant(string $variantPublicId, DateTimeImmutable $at): ?AvailabilityView;
}
