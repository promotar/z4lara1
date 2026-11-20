<?php

namespace Modules\Store\Domain\Catalog;

use InvalidArgumentException;

final readonly class Sku
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));

        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,63}$/', $value)) {
            throw new InvalidArgumentException('SKU must contain only letters, numbers, dots, underscores, or hyphens.');
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
