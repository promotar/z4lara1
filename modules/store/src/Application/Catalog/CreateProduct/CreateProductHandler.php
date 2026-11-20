<?php

namespace Modules\Store\Application\Catalog\CreateProduct;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\Events\ProductCreated;
use Modules\Store\Domain\Catalog\LocalizedSlug;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductTypeRegistry;
use Modules\Store\Domain\Catalog\Sku;
use Modules\Store\Domain\Catalog\VariantCombination;
use Modules\Store\Domain\Catalog\VariantStatus;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class CreateProductHandler
{
    public function __construct(
        private ProductTypeRegistry $types,
        private ProductRepository $products,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function handle(CreateProductCommand $command): CreateProductResult
    {
        $type = $this->types->get($command->typeCode);
        $locale = trim($command->defaultLocale);
        $name = trim($command->name);
        $slug = (string) new LocalizedSlug($command->slug);

        if ($locale === '' || strlen($locale) > 16 || ! preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $locale)) {
            throw new InvalidArgumentException('Default product locale is invalid.');
        }

        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Product name is required and cannot exceed 255 characters.');
        }

        $productPublicId = (string) $this->ids->generate();
        $defaultVariantPublicId = ! $type->supportsVariants ? (string) $this->ids->generate() : null;
        $sku = $command->defaultVariantSku === null ? null : (string) new Sku($command->defaultVariantSku);
        $now = $this->clock->now();

        $this->transactions->run(function () use (
            $command,
            $productPublicId,
            $defaultVariantPublicId,
            $locale,
            $name,
            $slug,
            $sku,
            $now,
        ): void {
            $productId = $this->products->createProduct(
                publicId: $productPublicId,
                typeCode: $command->typeCode,
                visibility: $command->visibility,
                defaultLocale: $locale,
                internalCode: $command->internalCode,
                actor: $command->actor,
                now: $now,
            );

            $this->products->addTranslation($productId, $locale, $name, $slug, $now);

            if ($defaultVariantPublicId !== null) {
                $this->products->createVariant(
                    productId: $productId,
                    publicId: $defaultVariantPublicId,
                    status: VariantStatus::Inactive,
                    sku: $sku,
                    isDefault: true,
                    combinationKey: (new VariantCombination([]))->key(),
                    actor: $command->actor,
                    now: $now,
                );
            }

            $this->events->afterCommit(new ProductCreated(
                productPublicId: $productPublicId,
                typeCode: $command->typeCode,
                occurredAt: $now,
                actorType: $command->actor->type,
                actorId: $command->actor->id,
            ));
        });

        return new CreateProductResult($productPublicId, $defaultVariantPublicId, 1);
    }
}
