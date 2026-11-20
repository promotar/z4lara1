<?php

namespace Modules\Exhibition\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class ExhibitionDeliveryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $recipient,
        private readonly string $safeSubject,
        private readonly string $safeBody,
    ) {}

    public function build(): self
    {
        return $this
            ->to($this->recipient)
            ->subject($this->safeSubject)
            ->text('exhibitions::mail.delivery', ['body' => $this->safeBody]);
    }
}
