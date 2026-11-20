<?php

namespace Modules\Store\Application\Catalog;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Store\Domain\Catalog\ProductRepository;

final readonly class ProductScopeService
{
    public function __construct(
        private ProductRepository $products,
        private ConnectionInterface $database,
    ) {}

    public function assertVariantBelongsToProduct(string $productPublicId, string $variantPublicId): void
    {
        if (! $this->products->variantBelongsToProduct($variantPublicId, $productPublicId)) {
            throw new InvalidArgumentException('Variant does not belong to the selected product.');
        }
    }

    public function isArtwork(string $productPublicId): bool
    {
        $product = $this->products->findByPublicId($productPublicId, true);

        if ($product === null) {
            return false;
        }

        return $product->typeCode === 'artwork'
            || $this->database->table('store_external_sellables')
                ->where('product_id', $product->id)
                ->exists();
    }
}
