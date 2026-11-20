<?php

namespace Modules\Store\Application\Catalog\Categories;

use Modules\Store\Domain\Shared\ActorContext;

final readonly class MoveCategoryCommand
{
    public function __construct(
        public string $categoryPublicId,
        public ?string $newParentPublicId,
        public int $expectedVersion,
        public ActorContext $actor,
    ) {}
}
