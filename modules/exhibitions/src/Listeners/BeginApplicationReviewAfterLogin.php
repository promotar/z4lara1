<?php

namespace Modules\Exhibition\Listeners;

use DomainException;
use Illuminate\Auth\Events\Login;
use Modules\Exhibition\Application\Applications\ArtistApplicationService;
use Modules\Exhibition\Domain\Applications\ApplicationState;

final readonly class BeginApplicationReviewAfterLogin
{
    public function __construct(private ArtistApplicationService $applications) {}

    public function handle(Login $event): void
    {
        $userId = (int) $event->user->getAuthIdentifier();
        $application = $this->applications->forUser($userId);

        if (
            $application === null
            || $application->state !== ApplicationState::Submitted->value
            || ! method_exists($event->user, 'hasVerifiedEmail')
            || ! $event->user->hasVerifiedEmail()
        ) {
            return;
        }

        // An administrator may verify the email without Laravel emitting the
        // Verified event. Reconcile the application on the next login while
        // leaving the core SafeReturnPath/account redirect contract untouched.
        try {
            $this->applications->transition(
                (string) $application->public_id,
                ApplicationState::UnderReview,
                $userId,
                'Email address verified. Application automatically moved to review.',
            );
        } catch (DomainException) {
            // A concurrent administrative action may already have changed it.
        }
    }
}
