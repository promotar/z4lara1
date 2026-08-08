<?php

namespace App\Platform\Core\Backups;

use App\Platform\Core\Models\BackupCheckpoint as BackupCheckpointModel;

class StepBackupper
{
    public function __construct(
        private readonly BackupManager $backups,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function afterStep(string $operationType, ?string $targetType, ?string $targetSlug, string $step, array $metadata = [], ?int $createdBy = null): BackupCheckpointModel
    {
        $checkpoint = $this->backups->createCheckpoint(
            $operationType,
            $targetType,
            $targetSlug,
            array_replace([
                'step' => $step,
                'checkpoint_reason' => 'Automatic checkpoint after successful step.',
            ], $metadata),
            'step-checkpoint',
            $createdBy,
        );

        $this->backups->addRestoreNote($checkpoint, "Automatic checkpoint created after successful step [{$step}] and before the next step.");

        return $this->backups->markCheckpointCompleted($checkpoint);
    }
}
