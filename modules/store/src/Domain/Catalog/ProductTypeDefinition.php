<?php

namespace Modules\Store\Domain\Catalog;

use InvalidArgumentException;

final readonly class ProductTypeDefinition
{
    /** @param list<string> $requiredAttributes */
    public function __construct(
        public string $code,
        public bool $supportsVariants,
        public bool $requiresShipping,
        public bool $supportsInventory,
        public array $requiredAttributes = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $code)) {
            throw new InvalidArgumentException('Product type code is invalid.');
        }
    }
}
