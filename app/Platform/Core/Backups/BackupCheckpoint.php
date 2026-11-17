<?php

namespace App\Platform\Core\Backups;

class BackupCheckpoint
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $operationType,
        public readonly ?string $targetType,
        public readonly ?string $targetSlug,
        public readonly string $checkpointType,
        public readonly array $metadata = [],
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation_type' => $this->operationType,
            'target_type' => $this->targetType,
            'target_slug' => $this->targetSlug,
            'checkpoint_type' => $this->checkpointType,
            'metadata' => $this->metadata,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
