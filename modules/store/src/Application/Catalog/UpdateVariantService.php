<?php

namespace Modules\Store\Application\Catalog;

use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\Sku;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class UpdateVariantService
{
    public function __construct(
        private ProductRepository $products,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function update(string $variantPublicId, int $expectedVersion, string $sku, ?string $barcode, int $position, ActorContext $actor): void
    {
        $sku = (string) new Sku($sku);
        $now = $this->clock->now();
        $this->transactions->run(function () use ($variantPublicId, $expectedVersion, $sku, $barcode, $position, $actor, $now): void {
            $variant = $this->products->findVariantByPublicId($variantPublicId, true)
                ?? throw new ProductNotFound('Product variant was not found.');
            if ($variant->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Variant version is stale.');
            }
            if (! $this->products->updateVariantIdentity($variant->id, $expectedVersion, $sku, $barcode, $position, $actor, $now)) {
                throw new ConcurrencyConflict('Variant update lost a concurrency race.');
            }
        });
    }
}
