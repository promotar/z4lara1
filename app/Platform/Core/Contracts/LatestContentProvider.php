<?php

namespace App\Platform\Core\Contracts;

use Illuminate\Support\Collection;

interface LatestContentProvider
{
    public function available(): bool;

    /** @return Collection<int, object> */
    public function latest(int $limit): Collection;

    /** @return Collection<int, object> */
    public function posts(int $limit, ?string $search = null): Collection;

    /** @return Collection<int, object> */
    public function categories(int $limit): Collection;

    /**
     * @param list<string> $terms
     * @return Collection<int, object>
     */
    public function search(array $terms, bool $includeUnpublished, int $limit): Collection;

    public function renderArchive(int $limit, string $search = ''): string;
}
