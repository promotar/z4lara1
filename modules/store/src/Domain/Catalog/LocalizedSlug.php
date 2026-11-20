<?php

namespace Modules\Store\Domain\Catalog;

use InvalidArgumentException;

final readonly class LocalizedSlug
{
    public string $value;

    public function __construct(string $value)
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        if ($value === '' || mb_strlen($value, 'UTF-8') > 255
            || ! preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', $value)) {
            throw new InvalidArgumentException('Localized slug is invalid.');
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
