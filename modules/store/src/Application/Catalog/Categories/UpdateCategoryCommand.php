<?php

namespace Modules\Store\Application\Catalog\Categories;

use Modules\Store\Domain\Shared\ActorContext;

final readonly class UpdateCategoryCommand
{
    public function __construct(
        public string $categoryPublicId,
        public int $expectedVersion,
        public string $locale,
        public string $name,
        public string $slug,
        public string $status,
        public int $position,
        public bool $featured,
        public ActorContext $actor,
        public ?string $description = null,
        public ?string $parentPublicId = null,
        public ?string $catalogSection = null,
    ) {}
}
