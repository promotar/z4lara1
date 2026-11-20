<?php

namespace Modules\Store\Application\Catalog;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\Events\VariantActivated;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\VariantStatus;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class VariantLifecycleService
{
    public function __construct(
        private ProductRepository $products,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function activate(string $variantPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($variantPublicId, $expectedVersion, $actor, $now): void {
            $variant = $this->products->findVariantByPublicId($variantPublicId, true)
                ?? throw new ProductNotFound('Product variant was not found.');
            if ($variant->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Variant version is stale.');
            }
            if ($variant->sku === null || trim($variant->sku) === '') {
                throw new InvalidArgumentException('An active variant must have a SKU.');
            }
            if ($variant->status === VariantStatus::Active) {
                return;
            }
            if (! $this->products->updateVariantStatus($variant->id, $expectedVersion, VariantStatus::Active, $actor, $now)) {
                throw new ConcurrencyConflict('Variant activation lost a concurrency race.');
            }
            $this->events->afterCommit(new VariantActivated($variantPublicId, $now));
        });
    }

    public function deactivate(string $variantPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->transition($variantPublicId, $expectedVersion, VariantStatus::Inactive, $actor);
    }

    public function archive(string $variantPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->transition($variantPublicId, $expectedVersion, VariantStatus::Archived, $actor);
    }

    private function transition(string $variantPublicId, int $expectedVersion, VariantStatus $status, ActorContext $actor): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($variantPublicId, $expectedVersion, $status, $actor, $now): void {
            $variant = $this->products->findVariantByPublicId($variantPublicId, true)
                ?? throw new ProductNotFound('Product variant was not found.');
            if ($variant->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Variant version is stale.');
            }
            if ($variant->status === $status) {
                return;
            }
            if (! $this->products->updateVariantStatus($variant->id, $expectedVersion, $status, $actor, $now)) {
                throw new ConcurrencyConflict('Variant status update lost a concurrency race.');
            }
        });
    }
}
