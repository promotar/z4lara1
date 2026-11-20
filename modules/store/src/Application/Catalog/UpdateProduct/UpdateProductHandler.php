<?php

namespace Modules\Store\Application\Catalog\UpdateProduct;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\LocalizedSlug;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class UpdateProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function handle(UpdateProductCommand $command): void
    {
        $locale = trim($command->locale);
        $name = trim($command->name);
        $slug = (string) new LocalizedSlug($command->slug);
        if ($locale === '' || strlen($locale) > 16 || ! preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $locale)) {
            throw new InvalidArgumentException('Product locale is invalid.');
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Product name is required and cannot exceed 255 characters.');
        }
        $artistName = trim((string) $command->artistName);
        if (mb_strlen($artistName, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Artist name cannot exceed 255 characters.');
        }
        $tags = array_values(array_unique(array_filter(array_map(
            static fn (mixed $tag): string => trim((string) $tag),
            $command->tags,
        ))));
        if (count($tags) > 30 || collect($tags)->contains(static fn (string $tag): bool => mb_strlen($tag, 'UTF-8') > 80)) {
            throw new InvalidArgumentException('Products support up to 30 tags, each no longer than 80 characters.');
        }

        $now = $this->clock->now();
        $this->transactions->run(function () use ($command, $locale, $name, $slug, $artistName, $tags, $now): void {
            $product = $this->products->findByPublicId($command->productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            if ($product->version !== $command->expectedVersion) {
                throw new ConcurrencyConflict('Product version is stale.');
            }
            if (! $this->products->updateProductDetails(
                $product->id,
                $command->expectedVersion,
                $command->visibility,
                $command->internalCode,
                $artistName === '' ? null : $artistName,
                $command->featured,
                $command->brandPublicId,
                $tags,
                $command->publishedAt,
                $command->actor,
                $now,
            )) {
                throw new ConcurrencyConflict('Product update lost a concurrency race.');
            }
            $this->products->upsertTranslation(
                $product->id,
                $locale,
                $name,
                $slug,
                $command->shortDescription,
                $command->description,
                $now,
            );
        });
    }
}
