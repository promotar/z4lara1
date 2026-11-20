<?php

namespace Modules\Store\Application\Catalog;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Store\Application\Catalog\UpdateProduct\UpdateProductCommand;
use Modules\Store\Application\Catalog\UpdateProduct\UpdateProductHandler;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Application\Media\ProductMediaService;
use Modules\Store\Application\Media\UploadProductImageService;
use Modules\Store\Application\Pricing\ScheduleSalePrice;
use Modules\Store\Application\Pricing\SetRegularPrice;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Inventory\InventoryRepository;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Media\MediaSource;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductEditorService
{
    public function __construct(
        private UpdateProductHandler $products,
        private ProductOrganizationService $organization,
        private SetRegularPrice $regularPrices,
        private ScheduleSalePrice $salePrices,
        private InventoryService $inventory,
        private UploadProductImageService $media,
        private ProductMediaService $gallery,
        private ProductPolicyService $policies,
        private ProductPublicationService $publisher,
        private ProductRepository $catalog,
        private InventoryRepository $inventoryRecords,
        private TransactionManager $transactions,
    ) {}

    public function save(ProductEditorUpdate $update): void
    {
        $this->transactions->run(function () use ($update): void {
            $this->products->handle(new UpdateProductCommand(
                $update->productPublicId,
                $update->expectedVersion,
                $update->locale,
                $update->name,
                $update->slug,
                $update->visibility,
                $update->actor,
                $update->internalCode,
                $update->artistName,
                $update->shortDescription,
                $update->description,
                $update->featured,
                $update->brandPublicId,
                $update->tags,
                $update->publishedAt,
            ));

            if ($update->syncCategories) {
                $this->organization->syncCategories($update->productPublicId, $update->categories, $update->primaryCategory);
            }

            $variant = $this->catalog->findDefaultVariantForProduct($update->productPublicId, true);
            if ($variant !== null) {
                $this->saveVariantCommerce($update, $variant->id, $variant->publicId);
            }

            $this->storeImage($update->productPublicId, $update->locale, $update->actor, $update->mainImage, MediaRole::Cover, true, 0);
            if ($update->mainAssetPublicId !== null) {
                $this->gallery->attach(
                    $update->productPublicId,
                    null,
                    new MediaSource(assetPublicId: $update->mainAssetPublicId),
                    MediaRole::Cover,
                    true,
                    0,
                    $update->locale,
                );
            }
            foreach ($update->gallery as $position => $image) {
                $this->storeImage($update->productPublicId, $update->locale, $update->actor, $image, MediaRole::Gallery, false, $position + 1);
            }
            foreach ($update->galleryAssetPublicIds as $position => $assetPublicId) {
                $this->gallery->attach(
                    $update->productPublicId,
                    null,
                    new MediaSource(assetPublicId: $assetPublicId),
                    MediaRole::Gallery,
                    false,
                    1000 + $position,
                    $update->locale,
                );
            }

            $this->policies->save(
                $update->productPublicId,
                $update->shippingPolicyMode,
                $update->shippingPolicy,
                $update->returnPolicyMode,
                $update->returnPolicy,
                $update->actor,
            );

            if ($update->publish) {
                $this->publisher->publish($update->productPublicId, $update->expectedVersion + 1, $update->actor);
            }
        });
    }

    private function saveVariantCommerce(ProductEditorUpdate $update, int $variantId, string $variantPublicId): void
    {
        if ($update->regularPrice !== null && $update->regularPrice !== '') {
            $this->regularPrices->execute($variantPublicId, Money::fromDecimal($update->regularPrice, 'JOD'), $update->actor);
        }
        if ($update->salePrice !== null && $update->salePrice !== '') {
            $this->salePrices->execute(
                $variantPublicId,
                Money::fromDecimal($update->salePrice, 'JOD'),
                $update->saleStartsAt ?? new DateTimeImmutable,
                $update->saleEndsAt,
                $update->actor,
                0,
                $update->replaceSalePublicId,
            );
        }
        if (! $update->updateInventory) {
            return;
        }

        $quantityProvided = $update->stockQuantity !== null;
        $tracking = $quantityProvided || $update->stockStatus === 'out_of_stock'
            ? InventoryTrackingMode::Tracked
            : InventoryTrackingMode::Untracked;
        $targetQuantity = $quantityProvided ? $update->stockQuantity : ($update->stockStatus === 'in_stock' ? 1 : 0);
        $item = $this->inventoryRecords->findItemByVariantId($variantId, true);

        if ($item === null) {
            $this->inventory->createItem(
                $variantPublicId,
                $tracking,
                $targetQuantity,
                $update->allowBackorder,
                0,
                $update->actor,
                'product-editor-'.$update->productPublicId.'-'.$update->expectedVersion,
            );

            return;
        }
        if ($targetQuantity < $item->reserved) {
            throw new InvalidArgumentException('Stock quantity cannot be lower than the quantity currently reserved.');
        }
        $this->inventory->updatePolicy(
            $item->publicId,
            $item->version,
            $tracking,
            $update->allowBackorder,
            $item->safetyStock,
            $item->reorderPoint,
        );
        if ($tracking === InventoryTrackingMode::Tracked && $targetQuantity !== $item->onHand) {
            $this->inventory->adjust(
                $item->publicId,
                $targetQuantity - $item->onHand,
                'product_editor',
                'product-editor-adjust-'.$update->productPublicId.'-'.$update->expectedVersion,
                $update->actor,
                'Updated from the product editor.',
            );
        }
    }

    private function storeImage(
        string $productPublicId,
        string $locale,
        ActorContext $actor,
        ?ProductEditorImage $image,
        MediaRole $role,
        bool $primary,
        int $position,
    ): void {
        if ($image === null) {
            return;
        }
        $this->media->upload(
            $productPublicId,
            null,
            $image->temporaryPath,
            $image->candidate,
            $role,
            $primary,
            $position,
            $actor,
            $locale,
        );
    }
}
