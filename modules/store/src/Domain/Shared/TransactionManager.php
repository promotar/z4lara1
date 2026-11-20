<?php

namespace Modules\Store\Domain\Shared;

interface TransactionManager
{
    /** @template T @param callable(): T $operation @return T */
    public function run(callable $operation): mixed;
}
