<?php

namespace Modules\Store\Infrastructure\Persistence\Catalog;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Catalog\CategoryMovePlan;
use Modules\Store\Domain\Catalog\CategoryNode;
use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;

final class DatabaseCategoryRepository implements CategoryRepository
{
    public function create(
        string $publicId,
        ?int $parentId,
        int $depth,
        int $position,
        ?string $code,
        ?string $catalogSection,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): int {
        return (int) DB::table('store_categories')->insertGetId([
            'public_id' => $publicId,
            'parent_id' => $parentId,
            'status' => 'active',
            'code' => $code === null ? null : strtolower(trim($code)),
            'catalog_section' => in_array($catalogSection, ['standard', 'clothing'], true) ? $catalogSection : null,
            'position' => $position,
            'depth' => $depth,
            'is_featured' => false,
            'version' => 1,
            'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addTranslation(
        int $categoryId,
        string $locale,
        string $name,
        string $slug,
        ?string $description,
        DateTimeImmutable $now,
    ): void {
        DB::table('store_category_translations')->insert([
            'category_id' => $categoryId,
            'locale' => $locale,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findByPublicId(string $publicId, bool $forUpdate = false): ?CategoryNode
    {
        $query = DB::table('store_categories')->where('public_id', $publicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first(['id', 'public_id', 'parent_id', 'depth', 'version']);

        return $row === null ? null : $this->toNode($row);
    }

    public function treeSnapshot(bool $forUpdate = false): array
    {
        $query = DB::table('store_categories')->orderBy('id');
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return array_map($this->toNode(...), $query->get(['id', 'public_id', 'parent_id', 'depth', 'version'])->all());
    }

    public function applyMove(CategoryNode $category, CategoryMovePlan $plan, int $expectedVersion, DateTimeImmutable $now): void
    {
        $updated = DB::table('store_categories')
            ->where('id', $category->id)
            ->where('version', $expectedVersion)
            ->update([
                'parent_id' => $plan->newParentId,
                'depth' => $plan->newDepth,
                'version' => $expectedVersion + 1,
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            throw new ConcurrencyConflict('Category move lost an optimistic concurrency race.');
        }

        if ($plan->depthDelta !== 0 && $plan->descendantIds !== []) {
            DB::table('store_categories')
                ->whereIn('id', $plan->descendantIds)
                ->increment('depth', $plan->depthDelta, ['updated_at' => $now]);
        }
    }

    private function toNode(object $row): CategoryNode
    {
        return new CategoryNode(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            parentId: $row->parent_id === null ? null : (int) $row->parent_id,
            depth: (int) $row->depth,
            version: (int) $row->version,
        );
    }

    public function activeIdsByPublicIds(array $publicIds): array
    {
        if ($publicIds === []) {
            return [];
        }

        return DB::table('store_categories')
            ->whereIn('public_id', array_values(array_unique($publicIds)))
            ->where('status', '!=', 'archived')
            ->orderBy('position')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function updateDetails(
        int $categoryId,
        int $expectedVersion,
        string $status,
        int $position,
        bool $featured,
        ?string $catalogSection,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool {
        return DB::table('store_categories')
            ->where('id', $categoryId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $status,
                'position' => max(0, $position),
                'is_featured' => $featured,
                'catalog_section' => in_array($catalogSection, ['standard', 'clothing'], true) ? $catalogSection : null,
                'archived_at' => $status === 'archived' ? $now : null,
                'version' => DB::raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]) === 1;
    }

    public function upsertTranslation(int $categoryId, string $locale, string $name, string $slug, ?string $description, DateTimeImmutable $now): void
    {
        DB::table('store_category_translations')->upsert([[
            'category_id' => $categoryId,
            'locale' => $locale,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['category_id', 'locale'], ['name', 'slug', 'description', 'updated_at']);
    }

    public function activeChildrenCount(int $categoryId): int
    {
        return DB::table('store_categories')->where('parent_id', $categoryId)->where('status', '!=', 'archived')->count();
    }

    public function detachProducts(int $categoryId): void
    {
        DB::table('store_product_categories')->where('category_id', $categoryId)->delete();
    }
}
