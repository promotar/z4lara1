<?php

namespace Modules\Store\Domain\Catalog;

use InvalidArgumentException;

final readonly class AttributeDefinition
{
    /** @param array<string, mixed> $validation */
    public function __construct(
        public string $code,
        public AttributeDataType $dataType,
        public bool $required = false,
        public bool $supportsVariantAxis = false,
        public array $validation = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]{0,127}$/', $code)) {
            throw new InvalidArgumentException('Attribute code is invalid.');
        }

        if ($supportsVariantAxis && $dataType !== AttributeDataType::Select) {
            throw new InvalidArgumentException('Only select attributes may support a variant axis.');
        }
    }
}
