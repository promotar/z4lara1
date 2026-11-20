<?php

namespace Modules\GoogleLogin\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\GoogleLogin\Models\GoogleAccount;

class GoogleAccountManager
{
    public function __construct(private readonly GoogleLoginSettings $settings)
    {
    }

    /** @param array{sub: string, email: string, name: string, picture: ?string} $profile */
    public function resolve(array $profile): User
    {
        return DB::transaction(function () use ($profile): User {
            $account = GoogleAccount::query()
                ->with('user')
                ->where('google_subject', $profile['sub'])
                ->lockForUpdate()
                ->first();

            if ($account?->user) {
                $this->refreshAccount($account, $profile);

                return $account->user;
            }

            $user = User::query()->where('email', $profile['email'])->lockForUpdate()->first();

            if (! $user) {
                if (! $this->settings->allowRegistration()) {
                    throw new GoogleLoginException('No local account matches this Google email. Contact an administrator for access.');
                }

                $user = new User();
                $user->forceFill([
                    'name' => $profile['name'],
                    'email' => $profile['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(64)),
                ])->save();

                $this->assignDefaultRole($user);
            }

            // Profiles reach this service only after Google has verified the
            // email claim. The matching local email must not enter the platform
            // email-code activation flow.
            $this->markMatchingGoogleEmailAsVerified($user, $profile['email']);

            $account = GoogleAccount::query()->updateOrCreate(
                ['google_subject' => $profile['sub']],
                [
                    'user_id' => $user->id,
                    'google_email' => $profile['email'],
                    'display_name' => $profile['name'],
                    'avatar_url' => $profile['picture'],
                    'last_login_at' => now(),
                ],
            );

            return $account->user()->firstOrFail();
        });
    }

    /** @param array{sub: string, email: string, name: string, picture: ?string} $profile */
    private function refreshAccount(GoogleAccount $account, array $profile): void
    {
        $account->forceFill([
            'google_email' => $profile['email'],
            'display_name' => $profile['name'],
            'avatar_url' => $profile['picture'],
            'last_login_at' => now(),
        ])->save();

        $this->markMatchingGoogleEmailAsVerified($account->user, $profile['email']);
    }

    private function markMatchingGoogleEmailAsVerified(User $user, string $googleEmail): void
    {
        $localEmail = strtolower(trim((string) $user->email));
        $googleEmail = strtolower(trim($googleEmail));

        if ($user->email_verified_at || $localEmail === '' || ! hash_equals($localEmail, $googleEmail)) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function assignDefaultRole(User $user): void
    {
        if (method_exists($user, 'assignRole') && Schema::hasTable('roles') && DB::table('roles')->where('name', 'user')->where('guard_name', 'web')->exists()) {
            $user->assignRole('user');
        }
    }
}
