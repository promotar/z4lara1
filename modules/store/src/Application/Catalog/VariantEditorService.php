<?php

namespace Modules\Store\Application\Catalog;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Application\Media\UploadProductImageService;
use Modules\Store\Application\Pricing\ScheduleSalePrice;
use Modules\Store\Application\Pricing\SetRegularPrice;
use Modules\Store\Domain\Inventory\InventoryRepository;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class VariantEditorService
{
    public function __construct(
        private UpdateVariantService $variants,
        private SetRegularPrice $regularPrices,
        private ScheduleSalePrice $salePrices,
        private InventoryService $inventory,
        private InventoryRepository $inventoryRecords,
        private UploadProductImageService $media,
        private TransactionManager $transactions,
    ) {}

    public function save(VariantEditorUpdate $update): void
    {
        $this->transactions->run(function () use ($update): void {
            $this->variants->update(
                $update->variantPublicId,
                $update->expectedVersion,
                $update->sku,
                $update->barcode,
                $update->position,
                $update->actor,
            );

            if ($update->regularPrice !== null && $update->regularPrice !== '') {
                $this->regularPrices->execute(
                    $update->variantPublicId,
                    Money::fromDecimal($update->regularPrice, 'JOD'),
                    $update->actor,
                );
            }

            if ($update->salePrice !== null && $update->salePrice !== '') {
                $this->salePrices->execute(
                    $update->variantPublicId,
                    Money::fromDecimal($update->salePrice, 'JOD'),
                    $update->saleStartsAt ?? new DateTimeImmutable,
                    $update->saleEndsAt,
                    $update->actor,
                    replacePricePublicId: $update->replaceSalePublicId,
                );
            }

            if ($update->updateInventory) {
                $this->saveInventory($update);
            }

            if ($update->image !== null) {
                $this->media->upload(
                    $update->productPublicId,
                    $update->variantPublicId,
                    $update->image->temporaryPath,
                    $update->image->candidate,
                    MediaRole::Cover,
                    true,
                    0,
                    $update->actor,
                    $update->locale,
                    $update->sku,
                );
            }
        });
    }

    private function saveInventory(VariantEditorUpdate $update): void
    {
        $variantId = $this->inventoryRecords->variantIdByPublicId($update->variantPublicId, true)
            ?? throw new InvalidArgumentException('Variant does not exist.');
        $quantityProvided = $update->stockQuantity !== null;
        $tracking = $quantityProvided || $update->stockStatus === 'out_of_stock'
            ? InventoryTrackingMode::Tracked
            : InventoryTrackingMode::Untracked;
        $targetQuantity = $quantityProvided
            ? $update->stockQuantity
            : ($update->stockStatus === 'in_stock' ? 1 : 0);
        $item = $this->inventoryRecords->findItemByVariantId($variantId, true);

        if ($item === null) {
            $this->inventory->createItem(
                $update->variantPublicId,
                $tracking,
                $targetQuantity,
                $update->allowBackorder,
                0,
                $update->actor,
                $update->idempotencyKey,
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
                'variant_editor',
                $update->idempotencyKey,
                $update->actor,
                'Updated while saving the product variant.',
            );
        }
    }
}
