<?php

namespace Modules\Store\Application\Catalog\Categories;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Catalog\CategoryTreeService;
use Modules\Store\Domain\Catalog\Events\CategoryMoved;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class MoveCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private CategoryTreeService $tree,
        private Clock $clock,
        private TransactionManager $transactions,
        private DomainEventPublisher $events,
    ) {}

    public function handle(MoveCategoryCommand $command): void
    {
        $now = $this->clock->now();

        $this->transactions->run(function () use ($command, $now): void {
            $category = $this->categories->findByPublicId($command->categoryPublicId, true)
                ?? throw new InvalidArgumentException('Category does not exist.');

            if ($category->version !== $command->expectedVersion) {
                throw new ConcurrencyConflict('Category was changed by another actor.');
            }

            $parent = $command->newParentPublicId === null
                ? null
                : $this->categories->findByPublicId($command->newParentPublicId, true);
            if ($command->newParentPublicId !== null && $parent === null) {
                throw new InvalidArgumentException('New parent category does not exist.');
            }

            $plan = $this->tree->planMove($category->id, $parent?->id, $this->categories->treeSnapshot(true));
            $this->categories->applyMove($category, $plan, $command->expectedVersion, $now);

            $this->events->afterCommit(new CategoryMoved(
                categoryPublicId: $category->publicId,
                parentPublicId: $command->newParentPublicId,
                occurredAt: $now,
                actorType: $command->actor->type,
                actorId: $command->actor->id,
            ));
        });
    }
}
