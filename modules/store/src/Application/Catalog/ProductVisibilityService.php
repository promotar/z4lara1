<?php

namespace Modules\Store\Application\Catalog;

use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductVisibilityService
{
    public function __construct(
        private ProductRepository $products,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function set(
        string $productPublicId,
        int $expectedVersion,
        ProductVisibility $visibility,
        ActorContext $actor,
    ): void {
        $this->transactions->run(function () use ($productPublicId, $expectedVersion, $visibility, $actor): void {
            $product = $this->products->findByPublicId($productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            if ($product->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Product version is stale.');
            }
            if (! $this->products->updateProductVisibility(
                $product->id,
                $expectedVersion,
                $visibility,
                $actor,
                $this->clock->now(),
            )) {
                throw new ConcurrencyConflict('Product visibility update lost a concurrency race.');
            }
        });
    }
}
