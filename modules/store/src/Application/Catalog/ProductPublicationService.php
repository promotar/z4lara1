<?php

namespace Modules\Store\Application\Catalog;

use Modules\Store\Application\Artwork\ArtworkPublicationGate;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\VariantStatus;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductPublicationService
{
    public function __construct(
        private ProductRepository $products,
        private VariantLifecycleService $variants,
        private ProductLifecycleService $lifecycle,
        private TransactionManager $transactions,
        private ArtworkPublicationGate $artworks,
    ) {}

    public function publish(string $productPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->artworks->assertEligible($productPublicId);
        $this->transactions->run(function () use ($productPublicId, $expectedVersion, $actor): void {
            foreach ($this->products->findVariantsForProduct($productPublicId, true) as $variant) {
                if ($variant->status === VariantStatus::Inactive) {
                    $this->variants->activate($variant->publicId, $variant->version, $actor);
                }
            }

            $this->lifecycle->publish($productPublicId, $expectedVersion, $actor);
        });
    }
}
