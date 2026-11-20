<?php

namespace Modules\Store\Domain\Catalog;

use InvalidArgumentException;
use Modules\Store\Domain\Shared\Exceptions\CategoryCycleDetected;

final readonly class CategoryTreeService
{
    public function __construct(private int $maximumDepth = 8) {}

    /** @param list<CategoryNode> $nodes */
    public function planMove(int $categoryId, ?int $newParentId, array $nodes): CategoryMovePlan
    {
        $byId = [];
        $children = [];

        foreach ($nodes as $node) {
            $byId[$node->id] = $node;
            if ($node->parentId !== null) {
                $children[$node->parentId][] = $node->id;
            }
        }

        $category = $byId[$categoryId]
            ?? throw new InvalidArgumentException('Category does not exist in the tree snapshot.');

        if ($newParentId === $categoryId) {
            throw new CategoryCycleDetected('A category cannot be its own parent.');
        }

        if ($newParentId !== null && ! isset($byId[$newParentId])) {
            throw new InvalidArgumentException('New parent category does not exist.');
        }

        $descendants = [];
        $queue = $children[$categoryId] ?? [];
        while ($queue !== []) {
            $candidate = array_shift($queue);
            if (isset($descendants[$candidate])) {
                throw new CategoryCycleDetected('Existing category tree contains a cycle.');
            }
            $descendants[$candidate] = true;
            array_push($queue, ...($children[$candidate] ?? []));
        }

        if ($newParentId !== null && isset($descendants[$newParentId])) {
            throw new CategoryCycleDetected('A category cannot be moved below one of its descendants.');
        }

        $newDepth = $newParentId === null ? 0 : $byId[$newParentId]->depth + 1;
        $delta = $newDepth - $category->depth;
        $maximumSubtreeDepth = $category->depth;
        foreach (array_keys($descendants) as $descendantId) {
            $maximumSubtreeDepth = max($maximumSubtreeDepth, $byId[$descendantId]->depth);
        }

        if ($maximumSubtreeDepth + $delta > $this->maximumDepth) {
            throw new InvalidArgumentException("Category move exceeds maximum depth {$this->maximumDepth}.");
        }

        return new CategoryMovePlan(
            newParentId: $newParentId,
            newDepth: $newDepth,
            depthDelta: $delta,
            descendantIds: array_map('intval', array_keys($descendants)),
        );
    }
}
