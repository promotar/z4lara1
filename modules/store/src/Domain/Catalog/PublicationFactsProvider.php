<?php

namespace Modules\Store\Domain\Catalog;

use DateTimeImmutable;

interface PublicationFactsProvider
{
    public function forProduct(ProductRecord $product, string $currency, DateTimeImmutable $at): PublicationFacts;
}
