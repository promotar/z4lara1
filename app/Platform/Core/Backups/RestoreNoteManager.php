<?php

namespace App\Platform\Core\Backups;

use App\Platform\Core\Models\BackupCheckpoint as BackupCheckpointModel;

class RestoreNoteManager
{
    public function add(BackupCheckpointModel $checkpoint, string $note): BackupCheckpointModel
    {
        $notes = trim((string) $checkpoint->notes);
        $checkpoint->notes = $notes === '' ? $note : $notes.PHP_EOL.PHP_EOL.$note;
        $checkpoint->save();

        return $checkpoint->refresh();
    }
}
