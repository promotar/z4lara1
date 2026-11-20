<?php

namespace Modules\Exhibition\Domain\Support;

enum SupportTicketState: string
{
    case Open = 'open';
    case AwaitingPlatform = 'awaiting_platform';
    case AwaitingArtist = 'awaiting_artist';
    case Closed = 'closed';

    public function label(): string
    {
        return __('exhibitions::ui.ticket_state_'.$this->value);
    }
}
