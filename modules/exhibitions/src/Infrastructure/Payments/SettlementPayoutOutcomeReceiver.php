<?php

namespace Modules\Exhibition\Infrastructure\Payments;

use Modules\Exhibition\Application\Finance\ArtistSettlementService;
use Modules\Exhibition\Contracts\Payments\V1\PayoutOutcome;
use Modules\Exhibition\Contracts\Payments\V1\PayoutOutcomeReceiver;

final readonly class SettlementPayoutOutcomeReceiver implements PayoutOutcomeReceiver
{
    public function __construct(private ArtistSettlementService $settlements) {}

    public function receive(PayoutOutcome $outcome): object
    {
        return $this->settlements->payoutOutcome(
            $outcome->settlementPublicId,
            $outcome->attemptIdempotencyKey,
            $outcome->providerReference,
            $outcome->paid,
            $outcome->failureReason,
            $outcome->actorUserId,
        );
    }
}
