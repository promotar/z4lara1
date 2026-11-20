<?php

namespace Modules\Store\Domain\Catalog;

final readonly class AssignedAttribute
{
    public function __construct(
        public AttributeRecord $attribute,
        public bool $required,
        public bool $variantAxis,
    ) {}
}
