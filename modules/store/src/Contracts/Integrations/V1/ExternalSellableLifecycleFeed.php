<?php

namespace Modules\Store\Contracts\Integrations\V1;

interface ExternalSellableLifecycleFeed
{
    /** @return list<ExternalSellableLifecycleRecord> */
    public function after(string $ownerCode, int $afterSequence, int $limit = 100): array;

    /** The order reference may be either the public ID or the human-facing order number. */
    public function customerOwnsOrder(string $ownerCode, string $orderReference, int $userId): bool;
}
