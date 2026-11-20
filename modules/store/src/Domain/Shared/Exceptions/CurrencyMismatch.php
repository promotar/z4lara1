<?php

namespace Modules\Store\Domain\Shared\Exceptions;

final class CurrencyMismatch extends StoreDomainException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct("Currency mismatch: expected {$expected}, received {$actual}.");
    }
}
