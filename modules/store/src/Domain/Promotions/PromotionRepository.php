<?php

namespace Modules\Store\Domain\Promotions;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

interface PromotionRepository
{
    public function create(
        string $publicId,
        string $name,
        ?string $code,
        string $mode,
        int $priority,
        PromotionStackingPolicy $stackingPolicy,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): int;

    /** @param list<array{type: string, public_id: ?string, target_key: string, include_descendants: bool}> $targets */
    public function addTargets(int $promotionId, array $targets, DateTimeImmutable $now): void;

    /** @param list<array{type: string, version: int, payload: array<string, mixed>}> $conditions */
    public function addConditions(int $promotionId, array $conditions, DateTimeImmutable $now): void;

    /** @param array{type: string, amount_minor: ?int, percentage_basis_points: ?int, currency: ?string, maximum_discount_minor: ?int} $action */
    public function addAction(int $promotionId, array $action, DateTimeImmutable $now): void;

    public function findByPublicId(string $publicId, bool $forUpdate = false): ?PromotionRecord;

    public function activate(int $promotionId, int $expectedVersion, ActorContext $actor, DateTimeImmutable $now): bool;

    public function transition(int $promotionId, int $expectedVersion, string $status, ActorContext $actor, DateTimeImmutable $now): bool;
}
