<?php

namespace Modules\Exhibition\Listeners;

use DomainException;
use Illuminate\Auth\Events\Verified;
use Modules\Exhibition\Application\Applications\ArtistApplicationService;
use Modules\Exhibition\Domain\Applications\ApplicationState;

final readonly class BeginApplicationReviewAfterEmailVerification
{
    public function __construct(private ArtistApplicationService $applications) {}

    public function handle(Verified $event): void
    {
        $userId = (int) $event->user->getAuthIdentifier();
        $application = $this->applications->forUser($userId);

        if ($application === null) {
            return;
        }

        if ($application->state !== ApplicationState::Submitted->value) {
            return;
        }

        try {
            $this->applications->transition(
                (string) $application->public_id,
                ApplicationState::UnderReview,
                $userId,
                'Email address verified. Application automatically moved to review.',
            );
        } catch (DomainException) {
            // A concurrent reviewer action may have already changed the application state.
        }
    }
}
