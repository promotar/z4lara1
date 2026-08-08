<?php

namespace App\Platform\Core\Rendering;

use App\Platform\Core\Contracts\EditorialContentProvider;
use Illuminate\Support\Collection;

final class NullEditorialContentProvider implements EditorialContentProvider
{
    public function available(): bool
    {
        return false;
    }

    public function editorialPosts(array $criteria, int $limit): Collection
    {
        return collect();
    }

    public function editorialCategories(int $limit, bool $withPublishedCount = false): Collection
    {
        return collect();
    }

    public function tags(int $limit): Collection
    {
        return collect();
    }

    public function postUrl(object $post): string
    {
        return '#';
    }

    public function categoryUrl(object $category): string
    {
        return '#';
    }

    public function tagUrl(object $tag): string
    {
        return '#';
    }

    public function indexUrl(): string
    {
        return '#';
    }

    public function featuredImageUrl(object $post): ?string
    {
        return null;
    }
}
