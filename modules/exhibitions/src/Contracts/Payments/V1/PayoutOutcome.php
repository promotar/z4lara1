<?php

namespace Modules\Exhibition\Contracts\Payments\V1;

use InvalidArgumentException;

/**
 * Framework-independent input accepted from an external payout provider or
 * an authorized finance operator recording that provider's final response.
 */
final readonly class PayoutOutcome
{
    public string $settlementPublicId;

    public string $attemptIdempotencyKey;

    public string $providerReference;

    public bool $paid;

    public ?string $failureReason;

    public ?int $actorUserId;

    public function __construct(
        string $settlementPublicId,
        string $attemptIdempotencyKey,
        string $providerReference,
        bool $paid,
        ?string $failureReason = null,
        ?int $actorUserId = null,
    ) {
        $settlementPublicId = trim($settlementPublicId);
        $attemptIdempotencyKey = trim($attemptIdempotencyKey);
        $providerReference = trim($providerReference);
        $failureReason = $failureReason === null ? null : trim($failureReason);

        if ($settlementPublicId === '' || $attemptIdempotencyKey === '' || $providerReference === '') {
            throw new InvalidArgumentException('Settlement, payout attempt, and provider reference are required.');
        }

        if (! $paid && ($failureReason === null || $failureReason === '')) {
            throw new InvalidArgumentException('A failed payout outcome requires the provider failure reason.');
        }

        if ($actorUserId !== null && $actorUserId < 1) {
            throw new InvalidArgumentException('The payout outcome actor must be a positive user identifier.');
        }

        $this->settlementPublicId = $settlementPublicId;
        $this->attemptIdempotencyKey = $attemptIdempotencyKey;
        $this->providerReference = $providerReference;
        $this->paid = $paid;
        $this->failureReason = $paid ? null : $failureReason;
        $this->actorUserId = $actorUserId;
    }
}
