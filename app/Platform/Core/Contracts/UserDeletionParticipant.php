<?php

namespace App\Platform\Core\Contracts;

use App\Models\User;

interface UserDeletionParticipant
{
    public function pluginSlug(): string;

    /** @return list<string> */
    public function blockers(User $user): array;

    /**
     * Remove only relations the plugin explicitly considers safe to discard.
     * This method is called inside the same transaction as the user deletion.
     */
    public function cleanup(User $user): void;
}
