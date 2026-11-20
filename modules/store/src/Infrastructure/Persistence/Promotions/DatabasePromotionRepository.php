<?php

namespace Modules\Store\Infrastructure\Persistence\Promotions;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Promotions\PromotionRecord;
use Modules\Store\Domain\Promotions\PromotionRepository;
use Modules\Store\Domain\Promotions\PromotionStackingPolicy;
use Modules\Store\Domain\Shared\ActorContext;

final class DatabasePromotionRepository implements PromotionRepository
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
    ): int {
        return (int) DB::table('store_promotions')->insertGetId([
            'public_id' => $publicId,
            'name' => $name,
            'code' => $code,
            'code_normalized' => $code === null ? null : strtoupper(trim($code)),
            'mode' => $mode,
            'status' => 'draft',
            'priority' => $priority,
            'stacking_policy' => $stackingPolicy->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addTargets(int $promotionId, array $targets, DateTimeImmutable $now): void
    {
        DB::table('store_promotion_targets')->insert(array_map(static fn (array $target): array => [
            'promotion_id' => $promotionId,
            'target_type' => $target['type'],
            'target_public_id' => $target['public_id'],
            'target_key' => $target['target_key'],
            'include_descendants' => $target['include_descendants'],
            'created_at' => $now,
        ], $targets));
    }

    public function addConditions(int $promotionId, array $conditions, DateTimeImmutable $now): void
    {
        if ($conditions === []) {
            return;
        }
        DB::table('store_promotion_conditions')->insert(array_map(static fn (array $condition): array => [
            'promotion_id' => $promotionId,
            'condition_type' => $condition['type'],
            'schema_version' => $condition['version'],
            'payload' => json_encode($condition['payload'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ], $conditions));
    }

    public function addAction(int $promotionId, array $action, DateTimeImmutable $now): void
    {
        DB::table('store_promotion_actions')->insert([
            'promotion_id' => $promotionId,
            'action_type' => $action['type'],
            'amount_minor' => $action['amount_minor'],
            'percentage_basis_points' => $action['percentage_basis_points'],
            'currency' => $action['currency'],
            'maximum_discount_minor' => $action['maximum_discount_minor'],
            'schema_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findByPublicId(string $publicId, bool $forUpdate = false): ?PromotionRecord
    {
        $query = DB::table('store_promotions')->where('public_id', $publicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first(['id', 'public_id', 'status', 'version']);

        return $row === null ? null : new PromotionRecord(
            (int) $row->id,
            (string) $row->public_id,
            (string) $row->status,
            (int) $row->version,
        );
    }

    public function activate(int $promotionId, int $expectedVersion, ActorContext $actor, DateTimeImmutable $now): bool
    {
        return DB::table('store_promotions')
            ->where('id', $promotionId)
            ->where('version', $expectedVersion)
            ->whereIn('status', ['draft', 'paused'])
            ->update([
                'status' => 'active',
                'activated_at' => $now,
                'version' => DB::raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]) === 1;
    }

    public function transition(int $promotionId, int $expectedVersion, string $status, ActorContext $actor, DateTimeImmutable $now): bool
    {
        return DB::table('store_promotions')
            ->where('id', $promotionId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $status,
                'archived_at' => $status === 'archived' ? $now : null,
                'version' => DB::raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]) === 1;
    }
}
