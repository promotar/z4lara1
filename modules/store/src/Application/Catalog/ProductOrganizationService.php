<?php

namespace Modules\Store\Application\Catalog;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductOrganizationService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /** @param list<string> $categoryPublicIds */
    public function syncCategories(string $productPublicId, array $categoryPublicIds, ?string $primaryCategoryPublicId): void
    {
        $categoryPublicIds = array_values(array_unique(array_filter(array_map('trim', $categoryPublicIds))));
        $primaryCategoryPublicId = trim((string) $primaryCategoryPublicId);
        if ($primaryCategoryPublicId === '' && $categoryPublicIds !== []) {
            $primaryCategoryPublicId = $categoryPublicIds[0];
        }
        if ($primaryCategoryPublicId !== null && $primaryCategoryPublicId !== '' && ! in_array($primaryCategoryPublicId, $categoryPublicIds, true)) {
            throw new InvalidArgumentException('Primary category must be one of the selected categories.');
        }

        $this->transactions->run(function () use ($productPublicId, $categoryPublicIds, $primaryCategoryPublicId): void {
            $product = $this->products->findByPublicId($productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            $categoryIds = $this->categories->activeIdsByPublicIds($categoryPublicIds);
            if (count($categoryIds) !== count($categoryPublicIds)) {
                throw new InvalidArgumentException('One or more selected categories are missing or archived.');
            }
            $primaryId = null;
            if ($primaryCategoryPublicId !== null && $primaryCategoryPublicId !== '') {
                $primary = $this->categories->activeIdsByPublicIds([$primaryCategoryPublicId]);
                $primaryId = $primary[0] ?? null;
            }
            $this->products->syncCategories($product->id, $categoryIds, $primaryId, $this->clock->now());
        });
    }
}
