<?php

namespace Modules\Store\Application\Catalog;

use Modules\Store\Domain\Catalog\Events\ProductArchived;
use Modules\Store\Domain\Catalog\Events\ProductPublished;
use Modules\Store\Domain\Catalog\Events\ProductRestored;
use Modules\Store\Domain\Catalog\ProductPublicationPolicy;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductStatus;
use Modules\Store\Domain\Catalog\ProductTypeRegistry;
use Modules\Store\Domain\Catalog\PublicationFactsProvider;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\Exceptions\ProductNotPublishable;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductLifecycleService
{
    public function __construct(
        private ProductRepository $products,
        private ProductTypeRegistry $types,
        private PublicationFactsProvider $facts,
        private ProductPublicationPolicy $policy,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function publish(string $productPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->transitionToPublished($productPublicId, $expectedVersion, $actor, false);
    }

    public function restore(string $productPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->transitionToPublished($productPublicId, $expectedVersion, $actor, true);
    }

    public function archive(string $productPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($productPublicId, $expectedVersion, $actor, $now): void {
            $product = $this->products->findByPublicId($productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            $this->assertVersion($product->version, $expectedVersion);
            if ($product->status === ProductStatus::Archived) {
                return;
            }
            if (! $this->products->updateProductStatus($product->id, $expectedVersion, ProductStatus::Archived, $actor, $now)) {
                throw new ConcurrencyConflict('Product archival lost a concurrency race.');
            }
            $this->events->afterCommit(new ProductArchived($productPublicId, $now));
        });
    }

    private function transitionToPublished(string $productPublicId, int $expectedVersion, ActorContext $actor, bool $restore): void
    {
        $now = $this->clock->now();
        $currency = (string) config('store.default_currency', 'JOD');

        $this->transactions->run(function () use ($productPublicId, $expectedVersion, $actor, $restore, $now, $currency): void {
            $product = $this->products->findByPublicId($productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            $this->assertVersion($product->version, $expectedVersion);

            if ($restore && $product->status !== ProductStatus::Archived) {
                throw new ProductNotPublishable(['only an archived product can be restored']);
            }
            if (! $restore && $product->status === ProductStatus::Archived) {
                throw new ProductNotPublishable(['archived products require the explicit restore command']);
            }
            if ($product->status === ProductStatus::Published) {
                return;
            }

            $violations = $this->policy->violations(
                $product,
                $this->types->get($product->typeCode),
                $this->facts->forProduct($product, $currency, $now),
            );
            if ($violations !== []) {
                throw new ProductNotPublishable($violations);
            }
            if (! $this->products->updateProductStatus($product->id, $expectedVersion, ProductStatus::Published, $actor, $now)) {
                throw new ConcurrencyConflict('Product publication lost a concurrency race.');
            }

            $this->events->afterCommit($restore
                ? new ProductRestored($productPublicId, $now)
                : new ProductPublished($productPublicId, $now, $actor->type, $actor->id));
        });
    }

    private function assertVersion(int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new ConcurrencyConflict("Product version is stale; expected [{$expected}], current [{$actual}].");
        }
    }
}
