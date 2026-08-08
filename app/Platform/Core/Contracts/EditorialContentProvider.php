<?php

namespace App\Platform\Core\Contracts;

use Illuminate\Support\Collection;

interface EditorialContentProvider
{
    public function available(): bool;

    /**
     * @param  array<string, string>  $criteria
     * @return Collection<int, object>
     */
    public function editorialPosts(array $criteria, int $limit): Collection;

    /** @return Collection<int, object> */
    public function editorialCategories(int $limit, bool $withPublishedCount = false): Collection;

    /** @return Collection<int, object> */
    public function tags(int $limit): Collection;

    public function postUrl(object $post): string;

    public function categoryUrl(object $category): string;

    public function tagUrl(object $tag): string;

    public function indexUrl(): string;

    public function featuredImageUrl(object $post): ?string;
}
