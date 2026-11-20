<?php

namespace Modules\Exhibition\Support;

use App\Models\User;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ExhibitionUserDeletionParticipant implements UserDeletionParticipant
{
    public function pluginSlug(): string
    {
        return 'exhibitions';
    }

    public function blockers(User $user): array
    {
        $checks = [
            ['exhibition_artist_identities', 'core_user_id', 'artist identity'],
            ['exhibition_artist_applications', 'core_user_id', 'artist application'],
            ['exhibition_fulfillments', 'artist_user_id', 'artist fulfillment record'],
            ['exhibition_fulfillments', 'customer_user_id', 'customer fulfillment record'],
            ['exhibition_settlements', 'artist_user_id', 'artist settlement'],
            ['exhibition_dispute_cases', 'artist_user_id', 'artist dispute'],
            ['exhibition_dispute_cases', 'customer_user_id', 'customer dispute'],
            ['exhibition_artwork_inquiries', 'customer_user_id', 'artwork inquiry'],
            ['exhibition_audit_entries', 'actor_user_id', 'audit record'],
        ];

        $blockers = [];
        foreach ($checks as [$table, $column, $label]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $user->getKey())->count();
            if ($count > 0) {
                $blockers[] = "has {$count} {$label}".($count === 1 ? '' : 's');
            }
        }

        return $blockers;
    }

    public function cleanup(User $user): void
    {
        // Artist, commerce, dispute, and audit history requires an explicit
        // domain workflow and must never be erased by generic user deletion.
    }
}
