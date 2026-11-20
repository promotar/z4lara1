<?php

namespace Modules\Store\Application\Promotions;

use InvalidArgumentException;
use Modules\Store\Domain\Promotions\PromotionActionType;
use Modules\Store\Domain\Promotions\PromotionConditionRegistry;
use Modules\Store\Domain\Promotions\PromotionRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\Exceptions\PromotionConflict;
use Modules\Store\Domain\Shared\PublicId;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class PromotionService
{
    public function __construct(
        private PromotionRepository $promotions,
        private PromotionConditionRegistry $conditions,
        private PromotionTargetValidator $targetValidator,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function create(CreatePromotionCommand $command): string
    {
        $name = trim($command->name);
        $mode = strtolower(trim($command->mode));
        $code = $command->code === null ? null : strtoupper(trim($command->code));
        if ($name === '' || ! in_array($mode, ['automatic', 'code'], true)) {
            throw new InvalidArgumentException('Promotion name or mode is invalid.');
        }
        if (($mode === 'code') !== ($code !== null && $code !== '')) {
            throw new InvalidArgumentException('Code-mode promotions require a code; automatic promotions must not have one.');
        }
        if ($command->startsAt !== null && $command->endsAt !== null && $command->startsAt >= $command->endsAt) {
            throw new PromotionConflict('Promotion end must be after its start.');
        }

        $targets = $this->targets($command->targets);
        $conditions = $this->conditions($command->conditions);
        $action = $this->action($command->action);
        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        $this->transactions->run(function () use (
            $command, $name, $mode, $code, $targets, $conditions, $action, $publicId, $now,
        ): void {
            $id = $this->promotions->create(
                $publicId,
                $name,
                $code,
                $mode,
                $command->priority,
                $command->stackingPolicy,
                $command->startsAt,
                $command->endsAt,
                $command->actor,
                $now,
            );
            $this->promotions->addTargets($id, $targets, $now);
            $this->promotions->addConditions($id, $conditions, $now);
            $this->promotions->addAction($id, $action, $now);
        });

        return $publicId;
    }

    public function activate(string $promotionPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($promotionPublicId, $expectedVersion, $actor, $now): void {
            $promotion = $this->promotions->findByPublicId($promotionPublicId, true)
                ?? throw new InvalidArgumentException('Promotion does not exist.');
            if ($promotion->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Promotion version is stale.');
            }
            if ($promotion->status === 'active') {
                return;
            }
            if (! $this->promotions->activate($promotion->id, $expectedVersion, $actor, $now)) {
                throw new ConcurrencyConflict('Promotion activation lost a concurrency race.');
            }
        });
    }

    public function pause(string $promotionPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->transition($promotionPublicId, $expectedVersion, 'paused', $actor);
    }

    public function archive(string $promotionPublicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->transition($promotionPublicId, $expectedVersion, 'archived', $actor);
    }

    private function transition(string $promotionPublicId, int $expectedVersion, string $status, ActorContext $actor): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($promotionPublicId, $expectedVersion, $status, $actor, $now): void {
            $promotion = $this->promotions->findByPublicId($promotionPublicId, true)
                ?? throw new InvalidArgumentException('Promotion does not exist.');
            if ($promotion->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Promotion version is stale.');
            }
            if ($promotion->status === $status) {
                return;
            }
            if (! $this->promotions->transition($promotion->id, $expectedVersion, $status, $actor, $now)) {
                throw new ConcurrencyConflict('Promotion status update lost a concurrency race.');
            }
        });
    }

    /** @param list<PromotionTargetInput> $inputs @return list<array{type: string, public_id: ?string, target_key: string, include_descendants: bool}> */
    private function targets(array $inputs): array
    {
        if ($inputs === []) {
            throw new PromotionConflict('Promotion requires at least one target.');
        }
        $targets = [];
        $seen = [];
        foreach ($inputs as $input) {
            if (! in_array($input->type, ['all_catalog', 'category', 'product', 'variant'], true)) {
                throw new PromotionConflict("Unknown promotion target [{$input->type}].");
            }
            if ($input->type === 'all_catalog') {
                if ($input->publicId !== null) {
                    throw new PromotionConflict('All-catalog promotion target cannot have a public ID.');
                }
                $publicId = null;
                $targetKey = 'all_catalog:*';
            } else {
                if ($input->publicId === null) {
                    throw new PromotionConflict("Promotion target [{$input->type}] requires a public ID.");
                }
                $publicId = (string) new PublicId($input->publicId);
                $this->targetValidator->assertExists($input->type, $publicId);
                $targetKey = $input->type.':'.$publicId;
            }
            if (isset($seen[$targetKey])) {
                throw new PromotionConflict('Promotion target is duplicated.');
            }
            $seen[$targetKey] = true;
            $targets[] = [
                'type' => $input->type,
                'public_id' => $publicId,
                'target_key' => $targetKey,
                'include_descendants' => $input->includeDescendants,
            ];
        }

        return $targets;
    }

    /** @param list<PromotionConditionInput> $inputs @return list<array{type: string, version: int, payload: array<string, mixed>}> */
    private function conditions(array $inputs): array
    {
        $conditions = [];
        foreach ($inputs as $input) {
            if (! $this->conditions->supports($input->type, $input->schemaVersion)) {
                throw new PromotionConflict("Unknown promotion condition or schema version [{$input->type}:{$input->schemaVersion}].");
            }
            $this->conditions->validate($input->type, $input->schemaVersion, $input->payload);
            $conditions[] = ['type' => $input->type, 'version' => $input->schemaVersion, 'payload' => $input->payload];
        }

        return $conditions;
    }

    /** @return array{type: string, amount_minor: ?int, percentage_basis_points: ?int, currency: ?string, maximum_discount_minor: ?int} */
    private function action(PromotionActionInput $input): array
    {
        $currency = $input->currency === null ? null : strtoupper($input->currency);
        if ($input->type === PromotionActionType::FixedAmountDiscount
            && ($input->amountMinor === null || $input->amountMinor <= 0 || $currency !== strtoupper((string) config('store.default_currency', 'JOD')))) {
            throw new PromotionConflict('Fixed promotion discount requires a positive amount in the operating currency.');
        }
        if ($input->type === PromotionActionType::PercentageDiscount
            && ($input->percentageBasisPoints === null || $input->percentageBasisPoints <= 0 || $input->percentageBasisPoints > 10_000)) {
            throw new PromotionConflict('Percentage promotion discount must be between 1 and 10000 basis points.');
        }
        if ($input->maximumDiscountMinor !== null && $input->maximumDiscountMinor <= 0) {
            throw new PromotionConflict('Promotion maximum discount must be positive.');
        }

        return [
            'type' => $input->type->value,
            'amount_minor' => $input->type === PromotionActionType::FixedAmountDiscount ? $input->amountMinor : null,
            'percentage_basis_points' => $input->type === PromotionActionType::PercentageDiscount ? $input->percentageBasisPoints : null,
            'currency' => $input->type === PromotionActionType::FixedAmountDiscount ? $currency : null,
            'maximum_discount_minor' => $input->maximumDiscountMinor,
        ];
    }
}
