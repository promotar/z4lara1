<?php

namespace Modules\Store\Domain\Catalog;

final readonly class CategoryNode
{
    public function __construct(
        public int $id,
        public string $publicId,
        public ?int $parentId,
        public int $depth,
        public int $version,
    ) {}
}
