<?php

namespace Modules\Store\Domain\Shared\Exceptions;

final class ProductNotPublishable extends StoreDomainException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('Product is not publishable: '.implode('; ', $violations));
    }
}
