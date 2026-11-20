<?php

namespace Modules\Store\Application\Catalog\UpdateProduct;

use DateTimeImmutable;
use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class UpdateProductCommand
{
    public function __construct(
        public string $productPublicId,
        public int $expectedVersion,
        public string $locale,
        public string $name,
        public string $slug,
        public ProductVisibility $visibility,
        public ActorContext $actor,
        public ?string $internalCode = null,
        public ?string $artistName = null,
        public ?string $shortDescription = null,
        public ?string $description = null,
        public bool $featured = false,
        public ?string $brandPublicId = null,
        /** @var list<string> */
        public array $tags = [],
        public ?DateTimeImmutable $publishedAt = null,
    ) {}
}
