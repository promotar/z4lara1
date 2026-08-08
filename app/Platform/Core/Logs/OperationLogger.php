<?php

namespace App\Platform\Core\Logs;

use App\Platform\Core\Models\OperationLog;

class OperationLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function start(string $operationType, ?string $targetType = null, ?string $targetSlug = null, array $context = [], ?int $createdBy = null): OperationLog
    {
        return OperationLog::query()->create([
            'operation_type' => $operationType,
            'target_type' => $targetType,
            'target_slug' => $targetSlug,
            'status' => OperationLog::STATUS_STARTED,
            'context' => $context,
            'started_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function success(OperationLog $operation, ?string $message = null, array $context = []): OperationLog
    {
        return $this->finish($operation, OperationLog::STATUS_SUCCESS, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function fail(OperationLog $operation, string $message, array $context = []): OperationLog
    {
        return $this->finish($operation, OperationLog::STATUS_FAILED, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function finish(OperationLog $operation, string $status, ?string $message, array $context): OperationLog
    {
        $operation->fill([
            'status' => $status,
            'message' => $message,
            'context' => array_replace($operation->context ?? [], $context),
            'finished_at' => now(),
        ])->save();

        return $operation->refresh();
    }
}
