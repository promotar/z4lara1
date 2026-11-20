<?php

namespace Modules\Store\Application\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Notifications\StoreOrderStatusNotification;
use Throwable;

final readonly class OrderNotificationService
{
    public function __construct(
        private OrderEventRecorder $events,
        private StoreSettingsService $settings,
        private StoreAdminNotificationService $adminNotifications,
    ) {}

    public function send(string $orderPublicId, bool $resend = false): bool
    {
        $order = DB::table('store_orders')->where('public_id', $orderPublicId)->first();
        if ($order === null || trim((string) $order->customer_email) === '') {
            return false;
        }
        $settings = $this->settings->current();
        $template = (array) ($settings['customer_notifications'][$order->status] ?? []);
        if (! $resend && ! (bool) ($template['enabled'] ?? false)) {
            return false;
        }
        $variables = [
            '{{customer_name}}' => (string) $order->customer_name,
            '{{order_number}}' => (string) $order->order_number,
            '{{order_status}}' => (string) $order->status,
            '{{store_name}}' => (string) $settings['store_name'],
        ];

        return $this->deliver(
            $order,
            new StoreOrderStatusNotification(
                (string) $order->order_number,
                (string) $order->status,
                configuredSubject: strtr((string) ($template['subject'] ?? ''), $variables),
                configuredBody: strtr((string) ($template['body'] ?? ''), $variables),
            ),
            ($resend ? 'Resent' : 'Sent')." {$order->status} notification to the customer.",
            'status',
        );
    }

    public function sendContactRequest(string $orderPublicId, string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('A customer-facing message is required.');
        }

        $order = DB::table('store_orders')->where('public_id', $orderPublicId)->first();
        if ($order === null || trim((string) $order->customer_email) === '') {
            return false;
        }

        return $this->deliver(
            $order,
            new StoreOrderStatusNotification((string) $order->order_number, (string) $order->status, $message),
            'Sent a customer contact request.',
            'contact_request',
            ['customer_message' => $message],
        );
    }

    /** @param array<string, mixed> $metadata */
    private function deliver(
        object $order,
        StoreOrderStatusNotification $notification,
        string $summary,
        string $type,
        array $metadata = [],
    ): bool {
        try {
            Notification::route('mail', (string) $order->customer_email)
                ->notify($notification);

            $this->events->record(
                (int) $order->id,
                'notification_sent',
                ActorContext::system('store-mail'),
                $summary,
                metadata: $metadata + ['channel' => 'mail', 'status' => (string) $order->status, 'type' => $type],
            );

            return true;
        } catch (Throwable $exception) {
            $this->events->record(
                (int) $order->id,
                'notification_failed',
                ActorContext::system('store-mail'),
                'Customer email could not be sent.',
                metadata: ['channel' => 'mail', 'type' => $type, 'exception' => $exception::class],
            );
            Log::warning('Store order notification failed.', [
                'order' => (string) $order->public_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->adminNotifications->send(
                'customer_email_failed',
                'Store customer email failed',
                "The customer email for order {$order->order_number} could not be sent.",
            );

            return false;
        }
    }
}
