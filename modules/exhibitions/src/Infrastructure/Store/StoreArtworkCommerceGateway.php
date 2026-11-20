<?php

namespace Modules\Exhibition\Infrastructure\Store;

use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceStatus;
use Throwable;

final readonly class StoreArtworkCommerceGateway implements ArtworkCommerceGateway
{
    public function __construct(private ExhibitionSettingsService $settings) {}

    public function status(string $artworkPublicId): ArtworkCommerceStatus
    {
        if (! (bool) $this->settings->value('exhibitions.store.enabled', true)) {
            return new ArtworkCommerceStatus(false, false, message: 'Purchasing is unavailable because the Exhibitions Store integration is disabled.');
        }
        $contract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway';
        if (! interface_exists($contract) || ! app()->bound($contract)) {
            return new ArtworkCommerceStatus(false, false, message: 'Store is unavailable.');
        }

        try {
            $status = app($contract)->status('exhibitions', $artworkPublicId);

            return new ArtworkCommerceStatus(
                true,
                (bool) $status->purchasable,
                $status->priceMinor,
                $status->currency,
                (int) $status->availableQuantity,
                (int) $status->maximumQuantity,
                $status->productStatus,
                $status->productVisibility,
                $status->variantStatus,
                $status->sellableState,
                $status->message,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new ArtworkCommerceStatus(false, false, message: 'Store availability could not be verified.');
        }
    }
}
