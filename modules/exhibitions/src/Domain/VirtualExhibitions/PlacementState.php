<?php

namespace Modules\Exhibition\Domain\VirtualExhibitions;

use DomainException;

enum PlacementState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Removed = 'removed';
    case Expired = 'expired';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::ChangesRequested, self::Approved, self::Rejected, self::Withdrawn],
            self::ChangesRequested => [self::Submitted, self::Withdrawn],
            self::Approved => [self::Published, self::Removed],
            self::Published => [self::Removed, self::Expired],
            default => [],
        };
    }

    public function assertTransitionTo(self $next): void
    {
        if (! in_array($next, $this->allowedTransitions(), true)) {
            throw new DomainException("Placement cannot transition from {$this->value} to {$next->value}.");
        }
    }

    public function reservesSlot(): bool
    {
        return in_array($this, [self::Submitted, self::ChangesRequested, self::Approved, self::Published], true);
    }
}
