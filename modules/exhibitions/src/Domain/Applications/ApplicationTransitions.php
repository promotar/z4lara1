<?php

namespace Modules\Exhibition\Domain\Applications;

use DomainException;

final class ApplicationTransitions
{
    /** @return list<ApplicationState> */
    public function allowedFrom(ApplicationState $state): array
    {
        return match ($state) {
            ApplicationState::Draft => [ApplicationState::Submitted],
            ApplicationState::Submitted => [ApplicationState::UnderReview, ApplicationState::DocumentsRequired, ApplicationState::Rejected],
            ApplicationState::UnderReview => [ApplicationState::DocumentsRequired, ApplicationState::Approved, ApplicationState::Rejected],
            ApplicationState::DocumentsRequired => [ApplicationState::Submitted, ApplicationState::UnderReview, ApplicationState::Rejected],
            ApplicationState::Approved => [ApplicationState::ContractPending],
            ApplicationState::ContractPending => [ApplicationState::Active, ApplicationState::Rejected],
            ApplicationState::Rejected => [ApplicationState::Submitted, ApplicationState::UnderReview],
            ApplicationState::Active => [],
        };
    }

    public function assertAllowed(ApplicationState $from, ApplicationState $to, ?string $reason, ?string $nextAction = null): void
    {
        if (! in_array($to, $this->allowedFrom($from), true)) {
            throw new DomainException("Invalid artist application transition from {$from->value} to {$to->value}.");
        }

        if ($to === ApplicationState::DocumentsRequired && trim((string) $nextAction) === '') {
            throw new DomainException("A clear document or information request is required when moving an application to {$to->value}.");
        }
    }
}
