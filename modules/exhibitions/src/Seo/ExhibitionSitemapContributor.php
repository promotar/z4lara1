<?php

namespace Modules\Exhibition\Seo;

use App\Platform\Core\Seo\SitemapContributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds published Exhibition artwork URLs to the sitemap while active.
 */
final class ExhibitionSitemapContributor implements SitemapContributor
{
    public function module(): string
    {
        return 'exhibitions';
    }

    public function sitemapEntries(): iterable
    {
        $columns = ['artwork.id', 'artwork.public_id', 'artwork.title', 'artwork.updated_at'];

        if (Schema::hasColumn('exhibition_artworks', 'slug')) {
            $columns[] = 'artwork.slug';
        }

        $artworks = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artwork_publications as publication', function ($join): void {
                $join->on('publication.artwork_id', '=', 'artwork.id')
                    ->where('publication.state', 'published');
            })
            ->where('artwork.state', 'published')
            ->orderBy('artwork.id')
            ->cursor($columns);

        foreach ($artworks as $artwork) {
            $permalink = filled($artwork->slug ?? null)
                ? (string) $artwork->slug
                : (Str::slug((string) ($artwork->title ?? '')) ?: (string) $artwork->public_id);

            yield [
                'loc' => route('exhibitions.artworks.show', ['artwork' => $permalink]),
                'lastmod' => $artwork->updated_at ? (string) $artwork->updated_at : null,
                'changefreq' => 'weekly',
                'priority' => 0.8,
            ];
        }
    }
}
