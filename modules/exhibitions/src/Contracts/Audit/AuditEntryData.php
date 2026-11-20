<?php

namespace Modules\Exhibition\Contracts\Audit;

use DateTimeImmutable;

final readonly class AuditEntryData
{
    /**
     * @param  array<string, mixed>|null  $previousValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $action,
        public string $targetType,
        public ?string $targetId = null,
        public ?array $previousValues = null,
        public ?array $newValues = null,
        public ?string $reason = null,
        public array $context = [],
        public ?int $actorUserId = null,
        public ?DateTimeImmutable $occurredAt = null,
        public ?string $idempotencyKey = null,
    ) {}
}
