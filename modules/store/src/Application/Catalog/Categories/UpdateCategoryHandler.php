<?php

namespace Modules\Store\Application\Catalog\Categories;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Catalog\CategoryTreeService;
use Modules\Store\Domain\Catalog\LocalizedSlug;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class UpdateCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private CategoryTreeService $tree,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function handle(UpdateCategoryCommand $command): void
    {
        $name = trim($command->name);
        $slug = (string) new LocalizedSlug($command->slug);
        if ($name === '' || ! in_array($command->status, ['active', 'inactive', 'archived'], true)) {
            throw new InvalidArgumentException('Category name or status is invalid.');
        }

        $now = $this->clock->now();
        $this->transactions->run(function () use ($command, $name, $slug, $now): void {
            $category = $this->categories->findByPublicId($command->categoryPublicId, true)
                ?? throw new InvalidArgumentException('Category does not exist.');
            if ($category->version !== $command->expectedVersion) {
                throw new ConcurrencyConflict('Category version is stale.');
            }
            if ($command->status === 'archived' && $this->categories->activeChildrenCount($category->id) > 0) {
                throw new InvalidArgumentException('Move or archive active child categories first.');
            }
            if ($command->status === 'archived') {
                // Archiving is intentionally non-destructive: products survive as uncategorized records.
                $this->categories->detachProducts($category->id);
            }
            $parent = $command->parentPublicId === null || $command->parentPublicId === ''
                ? null
                : $this->categories->findByPublicId($command->parentPublicId, true);
            if ($command->parentPublicId !== null && $command->parentPublicId !== '' && $parent === null) {
                throw new InvalidArgumentException('Parent category does not exist.');
            }
            $plan = $this->tree->planMove($category->id, $parent?->id, $this->categories->treeSnapshot(true));
            if ($plan->newParentId !== $category->parentId || $plan->depthDelta !== 0) {
                $this->categories->applyMove($category, $plan, $command->expectedVersion, $now);
                $category = $this->categories->findByPublicId($command->categoryPublicId, true)
                    ?? throw new InvalidArgumentException('Category does not exist.');
            }
            if (! $this->categories->updateDetails($category->id, $category->version, $command->status, $command->position, $command->featured, $command->catalogSection, $command->actor, $now)) {
                throw new ConcurrencyConflict('Category update lost a concurrency race.');
            }
            $this->categories->upsertTranslation($category->id, $command->locale, $name, $slug, $command->description, $now);
        });
    }
}
