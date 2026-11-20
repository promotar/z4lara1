<?php

namespace Modules\Store\Domain\Catalog;

final readonly class AttributeRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public AttributeDefinition $definition,
    ) {}
}
