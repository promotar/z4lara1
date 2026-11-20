<?php

namespace Modules\Store\Contracts\Integrations\V1;

interface ExternalSellableGateway
{
    public function upsert(ExternalSellableRequest $request): ExternalSellableResult;

    public function withdraw(string $ownerCode, string $externalId, string $reason, string $idempotencyKey): ExternalSellableResult;

    public function syncAvailability(string $ownerCode, string $externalId, string $state, int $quantity, string $idempotencyKey): ExternalSellableResult;

    public function reconcile(string $ownerCode, string $externalId): ExternalSellableResult;

    public function status(string $ownerCode, string $externalId): ExternalSellableStatus;
}
