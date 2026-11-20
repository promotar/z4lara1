<?php

namespace Modules\Exhibition\Domain\VirtualExhibitions;

use DomainException;

enum RoomState: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Published,
            self::Published => $next === self::Draft,
        };
    }

    public function assertTransitionTo(self $next): void
    {
        if ($next !== $this && ! $this->canTransitionTo($next)) {
            throw new DomainException("Room cannot transition from {$this->value} to {$next->value}.");
        }
    }
}
