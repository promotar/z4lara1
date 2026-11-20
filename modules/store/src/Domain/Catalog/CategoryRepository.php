<?php

namespace Modules\Store\Domain\Catalog;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

interface CategoryRepository
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
    ): int;

    public function addTranslation(
        int $categoryId,
        string $locale,
        string $name,
        string $slug,
        ?string $description,
        DateTimeImmutable $now,
    ): void;

    public function findByPublicId(string $publicId, bool $forUpdate = false): ?CategoryNode;

    /** @return list<CategoryNode> */
    public function treeSnapshot(bool $forUpdate = false): array;

    public function applyMove(CategoryNode $category, CategoryMovePlan $plan, int $expectedVersion, DateTimeImmutable $now): void;

    /** @param list<string> $publicIds @return list<int> */
    public function activeIdsByPublicIds(array $publicIds): array;

    public function updateDetails(
        int $categoryId,
        int $expectedVersion,
        string $status,
        int $position,
        bool $featured,
        ?string $catalogSection,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): bool;

    public function upsertTranslation(int $categoryId, string $locale, string $name, string $slug, ?string $description, DateTimeImmutable $now): void;

    public function activeChildrenCount(int $categoryId): int;

    public function detachProducts(int $categoryId): void;
}
