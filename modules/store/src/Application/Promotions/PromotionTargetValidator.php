<?php

namespace Modules\Store\Application\Promotions;

use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Shared\Exceptions\PromotionConflict;

final readonly class PromotionTargetValidator
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
    ) {}

    public function assertExists(string $type, string $publicId): void
    {
        $exists = match ($type) {
            'category' => $this->categories->findByPublicId($publicId) !== null,
            'product' => $this->products->findByPublicId($publicId) !== null,
            'variant' => $this->products->findVariantByPublicId($publicId) !== null,
            default => false,
        };

        if (! $exists) {
            throw new PromotionConflict("Promotion target [{$type}:{$publicId}] does not exist in Store.");
        }
    }
}
