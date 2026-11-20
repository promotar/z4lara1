<?php

namespace Modules\Store\Application\Orders;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Notifications\StoreAdminNotification;
use Throwable;

final readonly class StoreAdminNotificationService
{
    public function __construct(private StoreSettingsService $settings) {}

    public function send(string $type, string $subject, string $message): bool
    {
        $settings = (array) $this->settings->current()['admin_notifications'];
        if (! (bool) ($settings[$type] ?? false) || ($settings['recipients'] ?? []) === []) {
            return false;
        }
        try {
            Notification::route('mail', (array) $settings['recipients'])
                ->notify(new StoreAdminNotification($subject, $message));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Store admin notification failed.', [
                'type' => $type,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
