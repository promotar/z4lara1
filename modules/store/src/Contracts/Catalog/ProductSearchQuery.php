<?php

namespace Modules\Store\Contracts\Catalog;

final readonly class ProductSearchQuery
{
    public function __construct(
        public ProductReadContext $context,
        public string $status = 'published',
        public string $visibility = 'public',
        public ?string $categoryPublicId = null,
        public int $limit = 20,
        public int $offset = 0,
    ) {}
}
