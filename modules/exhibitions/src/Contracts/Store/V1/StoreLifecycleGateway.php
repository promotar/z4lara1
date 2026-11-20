<?php

namespace Modules\Exhibition\Contracts\Store\V1;

interface StoreLifecycleGateway
{
    public function available(): bool;

    /** @return list<StoreLifecycleRecord> */
    public function after(int $sequence, int $limit = 100): array;

    public function customerOwnsOrder(string $orderReference, int $coreUserId): bool;
}
