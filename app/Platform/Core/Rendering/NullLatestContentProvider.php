<?php

namespace App\Platform\Core\Rendering;

use App\Platform\Core\Contracts\LatestContentProvider;
use Illuminate\Support\Collection;

final class NullLatestContentProvider implements LatestContentProvider
{
    public function available(): bool
    {
        return false;
    }

    public function latest(int $limit): Collection
    {
        return collect();
    }

    public function posts(int $limit, ?string $search = null): Collection
    {
        return collect();
    }

    public function categories(int $limit): Collection
    {
        return collect();
    }

    public function search(array $terms, bool $includeUnpublished, int $limit): Collection
    {
        return collect();
    }

    public function renderArchive(int $limit, string $search = ''): string
    {
        return '';
    }
}
