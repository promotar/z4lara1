<?php

namespace Modules\Store\Application\Catalog\Categories;

use Modules\Store\Domain\Shared\ActorContext;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public string $locale,
        public string $name,
        public string $slug,
        public ActorContext $actor,
        public ?string $parentPublicId = null,
        public ?string $code = null,
        public int $position = 0,
        public ?string $description = null,
        public ?string $catalogSection = null,
    ) {}
}
