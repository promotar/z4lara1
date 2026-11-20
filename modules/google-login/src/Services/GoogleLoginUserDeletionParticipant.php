<?php

namespace Modules\GoogleLogin\Services;

use App\Models\User;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class GoogleLoginUserDeletionParticipant implements UserDeletionParticipant
{
    public function pluginSlug(): string
    {
        return 'google-login';
    }

    public function blockers(User $user): array
    {
        return [];
    }

    public function cleanup(User $user): void
    {
        if (Schema::hasTable('google_login_accounts')) {
            DB::table('google_login_accounts')->where('user_id', $user->getKey())->delete();
        }
    }
}
