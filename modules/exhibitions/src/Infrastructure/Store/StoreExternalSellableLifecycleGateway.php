<?php

namespace Modules\Exhibition\Infrastructure\Store;

use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleGateway;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleRecord;
use Throwable;

final class StoreExternalSellableLifecycleGateway implements StoreLifecycleGateway
{
    private const OWNER_CODE = 'exhibitions';

    public function available(): bool
    {
        return $this->feed() !== null;
    }

    public function after(int $sequence, int $limit = 100): array
    {
        $feed = $this->feed();

        if ($feed === null) {
            return [];
        }

        $records = $feed->after(self::OWNER_CODE, max(0, $sequence), min(500, max(1, $limit)));

        return array_values(array_map(
            static fn (object $record): StoreLifecycleRecord => StoreLifecycleRecord::fromExternal($record),
            is_array($records) ? $records : iterator_to_array($records),
        ));
    }

    public function customerOwnsOrder(string $orderReference, int $coreUserId): bool
    {
        $feed = $this->feed();

        if ($feed === null || trim($orderReference) === '' || $coreUserId < 1) {
            return false;
        }

        try {
            return (bool) $feed->customerOwnsOrder(self::OWNER_CODE, trim($orderReference), $coreUserId);
        } catch (Throwable) {
            return false;
        }
    }

    private function feed(): ?object
    {
        $contract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableLifecycleFeed';

        return interface_exists($contract) && app()->bound($contract) ? app($contract) : null;
    }
}
