<?php

namespace Modules\Exhibition\Infrastructure\Store;

use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationGateway;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationRequest;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationResult;

final readonly class StoreArtworkPublicationGateway implements ArtworkPublicationGateway
{
    public function __construct(private ExhibitionSettingsService $settings) {}

    public function publish(ArtworkPublicationRequest $request): ArtworkPublicationResult
    {
        $gateway = $this->storeGateway();
        if ($gateway === null) {
            return new ArtworkPublicationResult(false, 'failed', failureCode: 'store_unavailable', message: 'Store publication contract is unavailable or inactive.');
        }
        $requestClass = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableRequest';
        $external = new $requestClass(
            'exhibitions', $request->artworkPublicId, $request->approvedRevision, 'en', $request->title,
            $this->slug($request->title, $request->artworkPublicId), mb_substr($request->description, 0, 500),
            $request->description, $request->priceMinor, $request->currency, $request->availableQuantity,
            $request->approvedImageUrls, $request->idempotencyKey, $request->metadata,
        );
        $result = $gateway->upsert($external);

        return new ArtworkPublicationResult($result->succeeded, $result->state, $result->productPublicId, $result->failureCode, $result->message, $result->variantPublicId, $result->inventoryPublicId, $result->drift);
    }

    public function withdraw(string $artworkPublicId, string $reason, string $idempotencyKey): ArtworkPublicationResult
    {
        $gateway = $this->storeGateway();
        if ($gateway === null) {
            return new ArtworkPublicationResult(false, 'failed', failureCode: 'store_unavailable', message: 'Store publication contract is unavailable or inactive.');
        }
        $result = $gateway->withdraw('exhibitions', $artworkPublicId, $reason, $idempotencyKey);

        return new ArtworkPublicationResult($result->succeeded, $result->state, $result->productPublicId, $result->failureCode, $result->message, $result->variantPublicId, $result->inventoryPublicId, $result->drift);
    }

    public function reconcile(string $artworkPublicId): ArtworkPublicationResult
    {
        $gateway = $this->storeGateway();
        if ($gateway === null) {
            return new ArtworkPublicationResult(false, 'failed', failureCode: 'store_unavailable', message: 'Store publication contract is unavailable or inactive.');
        }
        $result = $gateway->reconcile('exhibitions', $artworkPublicId);

        return new ArtworkPublicationResult($result->succeeded, $result->state, $result->productPublicId, $result->failureCode, $result->message, $result->variantPublicId, $result->inventoryPublicId, $result->drift);
    }

    public function syncAvailability(string $artworkPublicId, int $availableQuantity, string $reason, string $idempotencyKey): ArtworkPublicationResult
    {
        $gateway = $this->storeGateway();
        if ($gateway === null) {
            return new ArtworkPublicationResult(false, 'failed', failureCode: 'store_unavailable', message: 'Store publication contract is unavailable or inactive.');
        }
        $result = $gateway->syncAvailability('exhibitions', $artworkPublicId, $availableQuantity > 0 ? 'available' : 'unavailable', $availableQuantity, $idempotencyKey);

        return new ArtworkPublicationResult($result->succeeded, $result->state, $result->productPublicId, $result->failureCode, $result->message, $result->variantPublicId, $result->inventoryPublicId, $result->drift);
    }

    private function storeGateway(): ?object
    {
        if (! (bool) $this->settings->value('exhibitions.store.enabled', true)) {
            return null;
        }
        $contract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway';

        return interface_exists($contract) && app()->bound($contract) ? app($contract) : null;
    }

    private function slug(string $title, string $id): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));

        return ($slug !== '' ? substr($slug, 0, 160).'-' : 'artwork-').substr(str_replace('-', '', $id), 0, 12);
    }
}
