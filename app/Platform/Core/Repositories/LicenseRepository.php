<?php

namespace App\Platform\Core\Repositories;

use App\Platform\Core\Models\License;
use Illuminate\Database\Eloquent\Collection;

class LicenseRepository
{
    /**
     * @return Collection<int, License>
     */
    public function all(): Collection
    {
        return License::query()
            ->orderBy('product_type')
            ->orderBy('product_slug')
            ->get();
    }

    public function findByKey(string $licenseKey): ?License
    {
        return License::query()
            ->where('license_key', $licenseKey)
            ->first();
    }

    /**
     * @return Collection<int, License>
     */
    public function findForProduct(string $productType, string $productSlug): Collection
    {
        return License::query()
            ->where('product_type', $productType)
            ->where('product_slug', $productSlug)
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): License
    {
        return License::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(License $license, array $attributes): License
    {
        $license->fill($attributes);
        $license->save();

        return $license->refresh();
    }

    public function deactivate(License $license): License
    {
        return $this->update($license, [
            'status' => License::STATUS_INACTIVE,
            'last_checked_at' => now(),
        ]);
    }

    public function delete(License $license): bool
    {
        return (bool) $license->delete();
    }
}
