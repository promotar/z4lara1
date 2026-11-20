<?php

namespace Modules\Store\Domain\Catalog;

final readonly class CategoryMovePlan
{
    /** @param list<int> $descendantIds */
    public function __construct(
        public ?int $newParentId,
        public int $newDepth,
        public int $depthDelta,
        public array $descendantIds,
    ) {}
}
