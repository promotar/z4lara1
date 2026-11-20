<?php

namespace Modules\Exhibition\Application\Applications;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Exhibition\Domain\Applications\ApplicationState;
use Modules\Exhibition\Notifications\ArtistApplicationStatusNotification;
use Throwable;

final class ApplicationNotificationService
{
    public function __construct(private readonly CoreUserGateway $users) {}

    public function sendOnce(object $application, string $type): bool
    {
        $key = implode(':', ['artist-application', $application->public_id, $type, $application->state]);
        $now = now();
        $inserted = DB::table('exhibition_application_notifications')->insertOrIgnore([
            'public_id' => (string) Str::uuid(),
            'application_id' => $application->id,
            'core_user_id' => $application->core_user_id,
            'type' => $type,
            'state' => $application->state,
            'idempotency_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted !== 1) {
            return false;
        }

        $email = $this->users->find((int) $application->core_user_id)?->email;
        if (! is_string($email) || trim($email) === '') {
            return false;
        }

        try {
            $state = ApplicationState::from($application->state);
            Notification::route('mail', $email)->notify(new ArtistApplicationStatusNotification(
                (string) $application->public_id,
                $state->label(),
                $application->current_reason,
                $application->next_action,
            ));

            DB::table('exhibition_application_notifications')
                ->where('idempotency_key', $key)
                ->update(['delivered_at' => now(), 'updated_at' => now()]);

            return true;
        } catch (Throwable $exception) {
            DB::table('exhibition_application_notifications')
                ->where('idempotency_key', $key)
                ->update(['failure_class' => $exception::class, 'updated_at' => now()]);
            Log::warning('Artist application notification failed.', [
                'application' => $application->public_id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
