<?php

namespace Modules\Store\Contracts\Catalog;

interface ProductReadService
{
    public function findByPublicId(string $publicId, ProductReadContext $context): ?ProductView;

    public function findBySlug(string $slug, ProductReadContext $context): ?ProductView;

    public function search(ProductSearchQuery $query): ProductPage;
}
