<?php

namespace Modules\Exhibition\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ArtistApplicationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $applicationPublicId,
        private readonly string $stateLabel,
        private readonly ?string $reason,
        private readonly ?string $nextAction,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Artist application: {$this->stateLabel}")
            ->greeting('Artist application update')
            ->line("Your application {$this->applicationPublicId} is now {$this->stateLabel}.");

        if ($this->reason !== null && trim($this->reason) !== '') {
            $message->line('Reason: '.$this->reason);
        }

        if ($this->nextAction !== null && trim($this->nextAction) !== '') {
            $message->line('Next action: '.$this->nextAction);
        }

        return $message->action('View application', url('/exhibitions/application'));
    }
}
