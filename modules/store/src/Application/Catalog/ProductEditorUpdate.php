<?php

namespace Modules\Store\Application\Catalog;

use DateTimeImmutable;
use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class ProductEditorUpdate
{
    /** @param list<string> $categories @param list<string> $tags @param list<ProductEditorImage> $gallery @param list<string> $galleryAssetPublicIds */
    public function __construct(
        public string $productPublicId,
        public int $expectedVersion,
        public string $locale,
        public string $name,
        public string $slug,
        public ProductVisibility $visibility,
        public ActorContext $actor,
        public ?string $internalCode,
        public ?string $artistName,
        public ?string $shortDescription,
        public ?string $description,
        public bool $featured,
        public ?string $brandPublicId,
        public array $tags,
        public ?DateTimeImmutable $publishedAt,
        public bool $syncCategories,
        public array $categories,
        public ?string $primaryCategory,
        public ?string $regularPrice,
        public ?string $salePrice,
        public ?DateTimeImmutable $saleStartsAt,
        public ?DateTimeImmutable $saleEndsAt,
        public ?string $replaceSalePublicId,
        public bool $updateInventory,
        public string $stockStatus,
        public ?int $stockQuantity,
        public bool $allowBackorder,
        public ?ProductEditorImage $mainImage,
        public ?string $mainAssetPublicId,
        public array $gallery,
        public array $galleryAssetPublicIds,
        public ?string $shippingPolicyMode,
        public ?string $shippingPolicy,
        public ?string $returnPolicyMode,
        public ?string $returnPolicy,
        public bool $publish,
    ) {}
}
