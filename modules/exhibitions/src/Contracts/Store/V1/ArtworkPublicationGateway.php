<?php

namespace Modules\Exhibition\Contracts\Store\V1;

interface ArtworkPublicationGateway
{
    public function publish(ArtworkPublicationRequest $request): ArtworkPublicationResult;

    public function withdraw(string $artworkPublicId, string $reason, string $idempotencyKey): ArtworkPublicationResult;

    public function reconcile(string $artworkPublicId): ArtworkPublicationResult;

    public function syncAvailability(string $artworkPublicId, int $availableQuantity, string $reason, string $idempotencyKey): ArtworkPublicationResult;
}
