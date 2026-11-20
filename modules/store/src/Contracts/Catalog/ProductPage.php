<?php

namespace Modules\Store\Contracts\Catalog;

final readonly class ProductPage
{
    /** @param list<ProductView> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {}
}
