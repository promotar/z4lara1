<?php

namespace Modules\Store\Application\Catalog;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class VariantEditorUpdate
{
    public function __construct(
        public string $productPublicId,
        public string $variantPublicId,
        public int $expectedVersion,
        public string $sku,
        public ?string $barcode,
        public int $position,
        public ActorContext $actor,
        public ?string $regularPrice = null,
        public ?string $salePrice = null,
        public ?DateTimeImmutable $saleStartsAt = null,
        public ?DateTimeImmutable $saleEndsAt = null,
        public ?string $replaceSalePublicId = null,
        public bool $updateInventory = false,
        public string $stockStatus = 'in_stock',
        public ?int $stockQuantity = null,
        public bool $allowBackorder = false,
        public ?ProductEditorImage $image = null,
        public ?string $locale = null,
        public string $idempotencyKey = '',
    ) {}
}
