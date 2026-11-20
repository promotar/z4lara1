<?php

namespace Modules\Store\Application\Catalog\CreateVariant;

use Modules\Store\Domain\Catalog\Events\VariantCreated;
use Modules\Store\Domain\Catalog\ProductAttributeRepository;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductTypeRegistry;
use Modules\Store\Domain\Catalog\Sku;
use Modules\Store\Domain\Catalog\VariantCombination;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Exceptions\DuplicateSku;
use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class CreateVariantHandler
{
    public function __construct(
        private ProductTypeRegistry $types,
        private ProductRepository $products,
        private ProductAttributeRepository $attributes,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function handle(CreateVariantCommand $command): CreateVariantResult
    {
        $sku = (string) new Sku($command->sku);
        $combination = new VariantCombination($command->options);

        if ($combination->options === []) {
            throw new InvalidVariantCombination('Explicit variants require at least one variant-axis option.');
        }

        $variantPublicId = (string) $this->ids->generate();
        $now = $this->clock->now();
        $isDefault = $command->isDefault;

        $this->transactions->run(function () use (
            $command,
            $sku,
            $combination,
            $variantPublicId,
            $now,
            &$isDefault,
        ): void {
            $product = $this->products->findByPublicId($command->productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            $type = $this->types->get($product->typeCode);

            if (! $type->supportsVariants) {
                throw new InvalidVariantCombination('A simple product cannot receive an explicit variant.');
            }

            if ($this->products->skuExists($sku)) {
                throw new DuplicateSku("SKU [{$sku}] already exists.");
            }

            if ($this->products->combinationExists($product->id, $combination->key())) {
                throw new InvalidVariantCombination('Variant option combination already exists for this product.');
            }

            $isDefault = $command->isDefault || ! $this->products->hasVariants($product->id);
            if ($isDefault) {
                $this->products->clearDefaultVariant($product->id, $now);
            }

            $axisOptions = $this->attributes->resolveVariantAxisOptions($product->id, $combination->options);
            $variantId = $this->products->createVariant(
                productId: $product->id,
                publicId: $variantPublicId,
                status: $command->status,
                sku: $sku,
                isDefault: $isDefault,
                combinationKey: $combination->key(),
                actor: $command->actor,
                now: $now,
            );
            $this->attributes->attachVariantOptions($variantId, $axisOptions, $now);

            $this->events->afterCommit(new VariantCreated(
                productPublicId: $product->publicId,
                variantPublicId: $variantPublicId,
                occurredAt: $now,
                actorType: $command->actor->type,
                actorId: $command->actor->id,
            ));
        });

        return new CreateVariantResult($variantPublicId, $sku, $combination->key(), $isDefault);
    }
}
