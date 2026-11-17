<?php

namespace App\Platform\Core\Backups;

use App\Platform\Core\Models\BackupCheckpoint as BackupCheckpointModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupManager
{
    public function __construct(
        private readonly RestoreNoteManager $notes,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createCheckpoint(string $operationType, ?string $targetType, ?string $targetSlug, array $metadata = [], string $checkpointType = 'metadata', ?int $createdBy = null): BackupCheckpointModel
    {
        $checkpoint = new BackupCheckpoint($operationType, $targetType, $targetSlug, $checkpointType, $metadata);
        $path = $this->writeCheckpointFile($checkpoint);

        $record = BackupCheckpointModel::query()->create([
            'operation_type' => $operationType,
            'target_type' => $targetType,
            'target_slug' => $targetSlug,
            'checkpoint_type' => $checkpointType,
            'status' => BackupCheckpointModel::STATUS_PENDING,
            'path' => $path,
            'metadata' => $metadata,
            'created_by' => $createdBy,
        ]);

        $this->pruneOldCheckpoints();

        return $record;
    }

    public function markCheckpointCompleted(BackupCheckpointModel $checkpoint): BackupCheckpointModel
    {
        return $this->mark($checkpoint, BackupCheckpointModel::STATUS_COMPLETED);
    }

    public function markCheckpointFailed(BackupCheckpointModel $checkpoint): BackupCheckpointModel
    {
        return $this->mark($checkpoint, BackupCheckpointModel::STATUS_FAILED);
    }

    public function addRestoreNote(BackupCheckpointModel $checkpoint, string $note): BackupCheckpointModel
    {
        return $this->notes->add($checkpoint, $note);
    }

    /**
     * @return Collection<int, BackupCheckpointModel>
     */
    public function getCheckpointsForTarget(string $targetType, string $targetSlug): Collection
    {
        return BackupCheckpointModel::query()
            ->where('target_type', $targetType)
            ->where('target_slug', $targetSlug)
            ->latest('id')
            ->get();
    }

    private function mark(BackupCheckpointModel $checkpoint, string $status): BackupCheckpointModel
    {
        $checkpoint->forceFill(['status' => $status])->save();

        return $checkpoint->refresh();
    }

    private function writeCheckpointFile(BackupCheckpoint $checkpoint): string
    {
        $directory = storage_path('app/platform/backup-checkpoints');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $slug = $checkpoint->targetSlug ?? 'platform';
        $name = now()->format('YmdHisv').'-'.$this->safeName($checkpoint->operationType).'-'.$this->safeName($slug).'-'.Str::lower(Str::random(6)).'.json';
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        File::put($path, json_encode($checkpoint->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function pruneOldCheckpoints(): void
    {
        BackupCheckpointModel::query()
            ->latest('created_at')
            ->latest('id')
            ->skip(10)
            ->take(1000)
            ->get()
            ->each(function (BackupCheckpointModel $checkpoint): void {
                $path = (string) $checkpoint->path;

                if ($this->isSafeCheckpointPath($path) && File::exists($path)) {
                    File::delete($path);
                }

                $checkpoint->delete();
            });
    }

    private function isSafeCheckpointPath(string $path): bool
    {
        if (trim($path) === '') {
            return false;
        }

        $base = realpath(storage_path('app/platform'));
        $resolved = realpath($path) ?: $path;

        return is_string($base) && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR);
    }

    private function safeName(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value), '-') ?: 'checkpoint';
    }
}
