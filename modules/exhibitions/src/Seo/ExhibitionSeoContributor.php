<?php

namespace Modules\Exhibition\Seo;

use App\Platform\Core\Seo\LocaleUrlStrategy;
use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Artworks\ArtworkTranslationService;
use Throwable;

/**
 * SEO adapter for public Exhibition artwork pages.
 */
final class ExhibitionSeoContributor implements SeoContributor
{
    public function module(): string
    {
        return 'exhibitions';
    }

    public function supports(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'exhibitions.artworks.index',
            'exhibitions.artworks.show',
            'exhibitions.virtual.index',
        ], true);
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            match ($request->route()?->getName()) {
                'exhibitions.artworks.show' => $this->artwork($seo, $request),
                'exhibitions.artworks.index' => $this->index($seo, 'Artworks', 'Browse original artworks exhibited through ART INPA.'),
                'exhibitions.virtual.index' => $this->index($seo, 'Virtual Exhibitions', 'Step inside immersive virtual exhibitions from ART INPA.'),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function artwork(SeoData $seo, Request $request): void
    {
        $permalink = (string) ($request->route('artwork') ?: $request->segment(2));
        $artwork = $this->publishedArtwork($permalink);

        if ($artwork === null) {
            return;
        }

        $translations = app(ArtworkTranslationService::class);
        $artwork = $translations->apply(
            $artwork,
            $translations->resolve((int) $artwork->id, app()->getLocale()),
        );

        $artist = DB::table('exhibition_artist_identities as identity')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('identity.id', $artwork->artist_identity_id)
            ->select('application.artistic_name', 'application.legal_name', 'application.biography')
            ->orderByDesc('application.updated_at')
            ->first();

        $artistName = trim((string) ($artist->artistic_name ?? $artist->legal_name ?? '')) ?: null;
        $localeUrls = app(LocaleUrlStrategy::class);
        $canonical = $localeUrls->withLocale(
            route('exhibitions.artworks.show', ['artwork' => $this->permalink($artwork)], false),
            app()->getLocale(),
        );
        $description = $this->text($artwork->meta_description ?? $artwork->short_description ?? null, 160);

        $seo->title = trim((string) ($artwork->seo_title ?: $artwork->title)) ?: null;
        $seo->description = $description;
        $seo->canonical = $canonical;
        $seo->ogType = 'website';
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $description;
        $seo->twitterTitle = $seo->title;
        $seo->twitterDescription = $description;

        $media = DB::table('exhibition_artwork_media')
            ->where('artwork_id', $artwork->id)
            ->where('state', 'ready')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->first();

        if ($media !== null) {
            $seo->image = route('exhibitions.artworks.media', [
                'artwork' => $artwork->public_id,
                'media' => $media->public_id,
            ]);
            $seo->imageAlt = $artwork->image_alt_text ?: $artwork->title;
            $seo->twitterImage = $seo->image;
            $seo->twitterCard = 'summary_large_image';
        }

        $seo->extra['exhibitions_artwork_id'] = (int) $artwork->id;
        $seo->extra['exhibitions_artwork_public_id'] = (string) $artwork->public_id;
        $seo->extra['content_title'] = strip_tags((string) ($artwork->title ?? ''));
        $seo->extra['content_description'] = strip_tags((string) ($artwork->short_description ?? $artwork->detailed_description ?? ''));
        $seo->extra['content_text'] = implode(' ', array_filter([
            strip_tags((string) ($artwork->title ?? '')),
            strip_tags((string) ($artwork->short_description ?? '')),
            strip_tags((string) ($artwork->detailed_description ?? '')),
            strip_tags((string) ($artwork->artwork_story ?? '')),
            strip_tags((string) ($artwork->inspiration_source ?? '')),
        ]));
        $seo->extra['replace_title'] = true;

        $seo->schema[] = $this->artworkSchema($artwork, $artistName, $seo, $canonical, $description);
        $seo->schema[] = $this->breadcrumbs([
            ['name' => 'Home', 'item' => $localeUrls->withLocale(url('/'), app()->getLocale())],
            ['name' => 'Artworks', 'item' => $localeUrls->withLocale(route('exhibitions.artworks.index', [], false), app()->getLocale())],
            ['name' => $artwork->title, 'item' => $canonical],
        ]);
    }

    private function index(SeoData $seo, string $title, string $description): void
    {
        $seo->title = $title;
        $seo->description = $description;
        $seo->ogTitle = $title;
        $seo->ogDescription = $description;
        $seo->extra['replace_title'] = true;
        $seo->schema[] = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $description,
            'url' => $seo->canonical,
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function publishedArtwork(string $permalink): ?object
    {
        $query = DB::table('exhibition_artworks')
            ->where('state', 'published')
            ->where(function ($query) use ($permalink): void {
                $query->where('public_id', $permalink);

                if (Schema::hasColumn('exhibition_artworks', 'slug')) {
                    $query->orWhere('slug', $permalink);
                }
            });

        $artwork = $query->first();

        if ($artwork === null) {
            return null;
        }

        $published = DB::table('exhibition_artwork_publications')
            ->where('artwork_id', $artwork->id)
            ->where('state', 'published')
            ->exists();

        return $published ? $artwork : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function artworkSchema(object $artwork, ?string $artistName, SeoData $seo, string $canonical, ?string $description): array
    {
        $price = DB::table('exhibition_artwork_pricing')
            ->where('artwork_id', $artwork->id)
            ->where('state', 'approved')
            ->first();

        $available = DB::table('exhibition_artwork_inventory_items')
            ->where('artwork_id', $artwork->id)
            ->where('state', 'available')
            ->exists();

        $offers = $price === null ? null : array_filter([
            '@type' => 'Offer',
            'price' => number_format((float) $price->approved_amount_snapshot, 2, '.', ''),
            'priceCurrency' => (string) $price->currency,
            'url' => $canonical,
            'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VisualArtwork',
            'name' => $artwork->title,
            'description' => $description,
            'url' => $canonical,
            'image' => $seo->image,
            'creator' => $artistName ? ['@type' => 'Person', 'name' => $artistName] : null,
            'artform' => $artwork->category ?? null,
            'artMedium' => $artwork->technique ?? null,
            'material' => $artwork->material ?? null,
            'dateCreated' => $artwork->completion_year ?? null,
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'offers' => $offers,
        ];

        return array_filter($schema, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function permalink(object $artwork): string
    {
        return filled($artwork->slug ?? null)
            ? (string) $artwork->slug
            : (Str::slug((string) ($artwork->title ?? '')) ?: (string) $artwork->public_id);
    }

    /**
     * @param  array<int, array{name: string, item: ?string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
            ];

            if (filled($item['item'])) {
                $entry['item'] = $item['item'];
            }

            $elements[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    private function text(?string $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($value, $limit);
    }
}
