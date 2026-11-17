<?php

namespace App\Platform\Core\Logs;

use App\Platform\Core\Models\OperationLog;
use Throwable;

class FailedOperationLogger
{
    public function __construct(
        private readonly OperationLogger $operations,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(OperationLog $operation, Throwable|string $failure, array $context = []): OperationLog
    {
        $message = $failure instanceof Throwable ? $failure->getMessage() : $failure;

        if ($failure instanceof Throwable) {
            $context['exception'] = $failure::class;
        }

        return $this->operations->fail($operation, $message, $context);
    }
}
