<?php

namespace Modules\Store\Domain\Promotions;

use InvalidArgumentException;

final readonly class PercentageBasisPoints
{
    private function __construct(public int $value) {}

    public static function fromPercentDecimal(string $percent): self
    {
        $normalized = trim($percent);
        if (! preg_match('/^(?:100(?:\.0{1,2})?|[0-9]{1,2}(?:\.[0-9]{1,2})?)$/', $normalized)) {
            throw new InvalidArgumentException('Percentage must be greater than 0 and no more than 100 with at most two decimals.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $basisPoints = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($basisPoints < 1 || $basisPoints > 10000) {
            throw new InvalidArgumentException('Percentage must be greater than 0 and no more than 100.');
        }

        return new self($basisPoints);
    }
}
