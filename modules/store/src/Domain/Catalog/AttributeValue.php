<?php

namespace Modules\Store\Domain\Catalog;

final readonly class AttributeValue
{
    public function __construct(
        public AttributeDataType $type,
        public string|int|bool $value,
    ) {}
}
