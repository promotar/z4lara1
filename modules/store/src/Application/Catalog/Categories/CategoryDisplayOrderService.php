<?php

namespace Modules\Store\Application\Catalog\Categories;

use Illuminate\Database\ConnectionInterface;

final class CategoryDisplayOrderService
{
    public function __construct(private readonly ConnectionInterface $database) {}

    public function move(string $categoryPublicId, int $displayPosition): void
    {
        $this->database->transaction(function () use ($categoryPublicId, $displayPosition): void {
            $category = $this->database->table('store_categories')
                ->where('public_id', $categoryPublicId)
                ->lockForUpdate()
                ->first(['id', 'parent_id']);

            if ($category === null) {
                return;
            }

            $siblingsQuery = $this->database->table('store_categories')
                ->when(
                    $category->parent_id === null,
                    fn ($query) => $query->whereNull('parent_id'),
                    fn ($query) => $query->where('parent_id', $category->parent_id),
                )
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate();

            $siblingIds = $siblingsQuery->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $categoryId = (int) $category->id;
            $siblingIds = array_values(array_filter($siblingIds, fn (int $id): bool => $id !== $categoryId));
            $targetIndex = min(max($displayPosition - 1, 0), count($siblingIds));

            array_splice($siblingIds, $targetIndex, 0, [$categoryId]);

            foreach ($siblingIds as $index => $siblingId) {
                $this->database->table('store_categories')
                    ->where('id', $siblingId)
                    ->update(['position' => $index + 1]);
            }
        });
    }
}
