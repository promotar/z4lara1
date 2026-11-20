<?php

namespace Modules\Store\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StoreOrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $orderNumber,
        private readonly string $status,
        private readonly ?string $message = null,
        private readonly ?string $configuredSubject = null,
        private readonly ?string $configuredBody = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = ucfirst(str_replace('_', ' ', $this->status));

        $mail = (new MailMessage)
            ->subject($this->configuredSubject ?: "Order {$this->orderNumber}: {$label}")
            ->greeting('Hello,');

        if ($this->configuredBody !== null) {
            $mail->line($this->configuredBody);
        } elseif ($this->message !== null) {
            $mail->line("The store needs your help with order {$this->orderNumber}.")
                ->line($this->message);
        } else {
            $mail->line("Your order {$this->orderNumber} is now {$label}.");
        }

        return $mail
            ->line('Payment method: Cash on Delivery.')
            ->line('Please contact the store if you need help with this order.');
    }
}
