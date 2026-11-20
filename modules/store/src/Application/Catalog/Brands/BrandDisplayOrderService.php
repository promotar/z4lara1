<?php

namespace Modules\Store\Application\Catalog\Brands;

use Illuminate\Database\ConnectionInterface;

final class BrandDisplayOrderService
{
    public function __construct(private readonly ConnectionInterface $database) {}

    public function move(string $brandPublicId, int $displayPosition): void
    {
        $this->database->transaction(function () use ($brandPublicId, $displayPosition): void {
            $brand = $this->database->table('store_brands')
                ->where('public_id', $brandPublicId)
                ->lockForUpdate()
                ->first(['id']);

            if ($brand === null) {
                return;
            }

            $brandId = (int) $brand->id;
            $brandIds = $this->database->table('store_brands')
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id !== $brandId)
                ->values()
                ->all();
            $targetIndex = min(max($displayPosition - 1, 0), count($brandIds));

            array_splice($brandIds, $targetIndex, 0, [$brandId]);

            foreach ($brandIds as $index => $siblingId) {
                $this->database->table('store_brands')
                    ->where('id', $siblingId)
                    ->update(['position' => $index + 1]);
            }
        });
    }
}
