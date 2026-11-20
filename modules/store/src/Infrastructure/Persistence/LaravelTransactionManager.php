<?php

namespace Modules\Store\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Shared\TransactionManager;

final class LaravelTransactionManager implements TransactionManager
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation, 3);
    }
}
