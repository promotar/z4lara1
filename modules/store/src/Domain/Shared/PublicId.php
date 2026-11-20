<?php

namespace Modules\Store\Domain\Shared;

use InvalidArgumentException;

final readonly class PublicId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value)) {
            throw new InvalidArgumentException('A Store public ID must be a canonical 26-character ULID.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
