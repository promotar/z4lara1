<?php

namespace Modules\Exhibition\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;

final class PageBuilderExtension
{
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-exhibitions-random-artworks\s*=)[^>]*>[\s\S]*?<\/div>/i';

    /** @var array<string, bool> */
    private array $tables = [];

    /** @var array<string, bool> */
    private array $columns = [];

    private ?bool $ready = null;

    public function __construct(
        private ArtworkCommerceGateway $commerce,
        private ArtworkEffectiveAvailabilityService $availability,
        private ExhibitionSettingsService $settings,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $extensions
     * @return array<int, array<string, mixed>>
     */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->isReady()) {
            return $extensions;
        }

        $preview = $this->publicArtworks(12, 'newest');
        $previewHtmlByOrder = collect(['random', 'newest', 'oldest', 'price-low', 'price-high'])
            ->mapWithKeys(fn (string $order): array => [
                $order => $this->cardsHtml($this->orderedArtworks($preview, $order)->take(12)->values()),
            ])
            ->all();
        $extensions[] = [
            'id' => 'exhibitions.random-artworks',
            'config' => [
                'previewHtmlByOrder' => $previewHtmlByOrder,
                'hasArtworks' => $preview->isNotEmpty(),
            ],
            'scripts' => [url('/platform/plugins/exhibitions/admin/js/vvveb-random-artworks.js?v=20260820-2')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->isReady() || ! str_contains($html, 'data-exhibitions-random-artworks')) {
            return $html;
        }

        if (! (bool) $this->settings->value('exhibitions.public_artwork_pages.enabled', true)) {
            return preg_replace(self::SLOT_PATTERN, '', $html) ?? $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->slotAttributes($match[0]);
            $limit = max(1, min(12, (int) ($attributes['data-exhibitions-artworks-limit'] ?? 3)));
            $order = $this->normalizeOrder((string) ($attributes['data-exhibitions-artworks-order'] ?? 'random'));
            $layout = $this->layoutSettings($attributes);
            $display = $this->displaySettings($attributes);
            $instance = $this->instanceKey($attributes);
            $artworks = $this->orderedArtworks($this->publicArtworks($limit, $order), $order)->take($limit)->values();

            if ($artworks->isEmpty()) {
                return '';
            }

            return '<section class="exhibitions-random-artworks" data-exhibitions-random-artworks-rendered="'.e($instance).'">'
                .$this->styles($instance, $layout, $display)
                .'<div class="exhibitions-random-artworks-grid">'
                .$this->cardsHtml($artworks)
                .'</div>'
                .'</section>';
        }, $html) ?? $html;
    }

    /** @return Collection<int, object> */
    private function publicArtworks(int $limit = 0, string $order = 'random'): Collection
    {
        $query = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artwork_publications as publication', function ($join): void {
                $join->on('publication.artwork_id', '=', 'artwork.id')
                    ->where('publication.state', 'published');
            })
            ->join('exhibition_artwork_pricing as pricing', function ($join): void {
                $join->on('pricing.artwork_id', '=', 'artwork.id')
                    ->where('pricing.state', 'approved');
            })
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->leftJoin('exhibition_artist_applications as application', function ($join): void {
                $join->on('application.core_user_id', '=', 'identity.core_user_id')
                    ->whereRaw('application.id = (select max(application_latest.id) from exhibition_artist_applications application_latest where application_latest.core_user_id = identity.core_user_id)');
            })
            ->where('artwork.state', 'published')
            ->whereNotNull('publication.store_product_public_id')
            ->whereNotNull('publication.store_variant_public_id')
            ->where(function ($query): void {
                $query->whereExists(fn ($inventory) => $inventory
                    ->selectRaw('1')
                    ->from('exhibition_artwork_inventory_items as available_inventory')
                    ->whereColumn('available_inventory.artwork_id', 'artwork.id')
                    ->where('available_inventory.state', 'available'))
                    ->orWhere(function ($query): void {
                        $query->whereExists(fn ($inventory) => $inventory
                            ->selectRaw('1')
                            ->from('exhibition_artwork_inventory_items as unavailable_sale_inventory')
                            ->whereColumn('unavailable_sale_inventory.artwork_id', 'artwork.id')
                            ->whereIn('unavailable_sale_inventory.state', ['reserved', 'sold']))
                            ->whereNotExists(fn ($inventory) => $inventory
                                ->selectRaw('1')
                                ->from('exhibition_artwork_inventory_items as remaining_inventory')
                                ->whereColumn('remaining_inventory.artwork_id', 'artwork.id')
                                ->where('remaining_inventory.state', 'available'));
                    })
                    ->orWhere(function ($query): void {
                        $this->availability->constrainToConfirmedSale($query, 'artwork.id');
                    });
            })
            ->select(
                'artwork.id',
                'artwork.public_id',
                'artwork.title',
                'artwork.category',
                'artwork.short_description',
                'artwork.completion_year',
                'artwork.published_at',
                'pricing.approved_amount_snapshot',
                'pricing.currency',
                'application.artistic_name',
                'application.legal_name',
            )
            ->when($this->hasColumn('exhibition_artworks', 'slug'), fn ($query) => $query->addSelect('artwork.slug'));

        if ($limit > 0) {
            match ($this->normalizeOrder($order)) {
                'newest' => $query->orderByDesc('artwork.published_at')->orderByDesc('artwork.id'),
                'oldest' => $query->orderBy('artwork.published_at')->orderBy('artwork.id'),
                'price-low' => $query->orderBy('pricing.approved_amount_snapshot')->orderBy('artwork.id'),
                'price-high' => $query->orderByDesc('pricing.approved_amount_snapshot')->orderByDesc('artwork.id'),
                default => $query->inRandomOrder(),
            };

            // Fetch a small pool so the purchasable filter still has options,
            // then enrich only those rows instead of every published artwork.
            $query->limit(max(12, $limit * 4));
        }

        $artworks = $query->get();

        if ($artworks->isEmpty()) {
            return $artworks;
        }

        $commerceStatuses = [];

        foreach ($artworks as $artwork) {
            $commerceStatuses[(int) $artwork->id] = $this->commerce->status((string) $artwork->public_id);
        }

        $availability = $this->availability->forArtworks(
            array_keys($commerceStatuses),
            array_map(static fn ($status): ?string => $status->availability, $commerceStatuses),
        );

        $artworks = $artworks->filter(function (object $artwork) use ($commerceStatuses, $availability): bool {
            $id = (int) $artwork->id;
            $commerce = $commerceStatuses[$id];
            $effectiveAvailability = $availability[$id] ?? $commerce->availability;
            $artwork->is_sold = in_array($effectiveAvailability, ['reserved', 'sold'], true);

            return $artwork->is_sold || $commerce->purchasable;
        })->values();

        if ($limit > 0) {
            $artworks = $artworks->take($limit)->values();
        }

        if ($artworks->isEmpty()) {
            return $artworks;
        }

        $images = DB::table('exhibition_artwork_media')
            ->whereIn('artwork_id', $artworks->pluck('id'))
            ->where('state', 'ready')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('artwork_id');

        return $artworks->each(function (object $artwork) use ($images): void {
            $artwork->primary_media = $images->get($artwork->id)?->first();
        });
    }

    /**
     * @param Collection<int, object> $artworks
     * @return Collection<int, object>
     */
    private function orderedArtworks(Collection $artworks, string $order): Collection
    {
        return match ($this->normalizeOrder($order)) {
            'newest' => $artworks->sort(function (object $left, object $right): int {
                return strcmp((string) $right->published_at, (string) $left->published_at)
                    ?: ((int) $right->id <=> (int) $left->id);
            })->values(),
            'oldest' => $artworks->sort(function (object $left, object $right): int {
                return strcmp((string) $left->published_at, (string) $right->published_at)
                    ?: ((int) $left->id <=> (int) $right->id);
            })->values(),
            'price-low' => $artworks->sort(function (object $left, object $right): int {
                return ((float) $left->approved_amount_snapshot <=> (float) $right->approved_amount_snapshot)
                    ?: ((int) $left->id <=> (int) $right->id);
            })->values(),
            'price-high' => $artworks->sort(function (object $left, object $right): int {
                return ((float) $right->approved_amount_snapshot <=> (float) $left->approved_amount_snapshot)
                    ?: ((int) $right->id <=> (int) $left->id);
            })->values(),
            default => $artworks->shuffle()->values(),
        };
    }

    private function normalizeOrder(string $order): string
    {
        return in_array($order, ['random', 'newest', 'oldest', 'price-low', 'price-high'], true)
            ? $order
            : 'random';
    }

    /** @param Collection<int, object> $artworks */
    private function cardsHtml(Collection $artworks): string
    {
        return $artworks->map(function (object $artwork): string {
            $permalink = filled($artwork->slug ?? null)
                ? (string) $artwork->slug
                : (Str::slug((string) $artwork->title) ?: (string) $artwork->public_id);
            $url = route('exhibitions.artworks.show', $permalink, false);
            $artist = trim((string) ($artwork->artistic_name ?: $artwork->legal_name ?: config('app.name', 'Art INPA')));
            $price = rtrim(rtrim(number_format((float) $artwork->approved_amount_snapshot, 3, '.', ''), '0'), '.');
            $image = $artwork->primary_media
                ? '<img src="'.e(route('exhibitions.artworks.media', [$artwork->public_id, $artwork->primary_media->public_id], false)).'" alt="'.e((string) $artwork->title).'" loading="lazy">'
                : '<span class="exhibitions-random-artworks-placeholder" aria-hidden="true">ART</span>';

            return '<article class="exhibitions-random-artwork-card" data-artwork-id="'.e((string) $artwork->public_id).'" data-artwork-state="'.($artwork->is_sold ? 'sold' : 'available').'">'
                .'<a class="exhibitions-random-artwork-image" href="'.e($url).'">'
                .$image
                .'<em class="exhibitions-random-artwork-state">'.($artwork->is_sold ? 'Sold' : 'Available').'</em>'
                .'</a>'
                .'<div class="exhibitions-random-artwork-body">'
                .'<div class="exhibitions-random-artwork-meta"><span>'.e((string) ($artwork->category ?: 'Artwork')).'</span>'
                .($artwork->completion_year ? '<span>'.e((string) $artwork->completion_year).'</span>' : '').'</div>'
                .'<h3><a href="'.e($url).'">'.e((string) $artwork->title).'</a></h3>'
                .'<p class="exhibitions-random-artwork-artist">'.e($artist).'</p>'
                .'<strong class="exhibitions-random-artwork-price">'.e($price.' '.strtoupper((string) $artwork->currency)).'</strong>'
                .'</div>'
                .'</article>';
        })->implode('');
    }

    /** @return array{desktop:int, tablet:int, mobile:int, gap:int, image_fit:string} */
    private function layoutSettings(array $attributes): array
    {
        $imageFit = (string) ($attributes['data-exhibitions-artworks-image-fit'] ?? 'contain');

        return [
            'desktop' => max(1, min(6, (int) ($attributes['data-exhibitions-artworks-columns-desktop'] ?? 3))),
            'tablet' => max(1, min(4, (int) ($attributes['data-exhibitions-artworks-columns-tablet'] ?? 2))),
            'mobile' => max(1, min(2, (int) ($attributes['data-exhibitions-artworks-columns-mobile'] ?? 1))),
            'gap' => max(0, min(120, (int) ($attributes['data-exhibitions-artworks-gap'] ?? 24))),
            'image_fit' => in_array($imageFit, ['contain', 'cover'], true) ? $imageFit : 'contain',
        ];
    }

    /** @return array{artist:bool, category:bool, price:bool, state:bool} */
    private function displaySettings(array $attributes): array
    {
        return [
            'artist' => ($attributes['data-exhibitions-artworks-show-artist'] ?? '1') !== '0',
            'category' => ($attributes['data-exhibitions-artworks-show-category'] ?? '1') !== '0',
            'price' => ($attributes['data-exhibitions-artworks-show-price'] ?? '1') !== '0',
            'state' => ($attributes['data-exhibitions-artworks-show-state'] ?? '1') !== '0',
        ];
    }

    /**
     * @param array{desktop:int, tablet:int, mobile:int, gap:int, image_fit:string} $layout
     * @param array{artist:bool, category:bool, price:bool, state:bool} $display
     */
    private function styles(string $instance, array $layout, array $display): string
    {
        $selector = '[data-exhibitions-random-artworks-rendered="'.e($instance).'"]';
        $hidden = [];
        if (! $display['artist']) $hidden[] = '.exhibitions-random-artwork-artist';
        if (! $display['category']) $hidden[] = '.exhibitions-random-artwork-meta';
        if (! $display['price']) $hidden[] = '.exhibitions-random-artwork-price';
        if (! $display['state']) $hidden[] = '.exhibitions-random-artwork-state';
        $hiddenRule = $hidden === [] ? '' : $selector.' '.implode(','.$selector.' ', $hidden).'{display:none}';

        return '<style data-exhibitions-random-artworks-styles>'
            .$selector.'{display:block;width:100%}'
            .$selector.' .exhibitions-random-artworks-grid{display:grid;grid-template-columns:repeat('.$layout['desktop'].',minmax(0,1fr));gap:'.$layout['gap'].'px}'
            .$selector.' .exhibitions-random-artwork-card{min-width:0;overflow:hidden;border:1px solid rgba(15,23,42,.12);border-radius:14px;background:#fff;color:#0f172a;box-shadow:0 8px 24px rgba(15,23,42,.07)}'
            .$selector.' .exhibitions-random-artwork-image{position:relative;display:flex;width:100%;aspect-ratio:4/3;align-items:center;justify-content:center;overflow:hidden;background:#f3f4f6;text-decoration:none}'
            .$selector.' .exhibitions-random-artwork-image img{display:block;width:100%;height:100%;object-fit:'.$layout['image_fit'].'}'
            .$selector.' .exhibitions-random-artworks-placeholder{font:800 2rem/1 system-ui,sans-serif;letter-spacing:.18em;color:#94a3b8}'
            .$selector.' .exhibitions-random-artwork-state{position:absolute;inset-block-start:12px;inset-inline-end:12px;border-radius:999px;background:rgba(15,23,42,.86);padding:6px 10px;color:#fff;font:700 12px/1 system-ui,sans-serif;font-style:normal}'
            .$selector.' .exhibitions-random-artwork-card[data-artwork-state="available"] .exhibitions-random-artwork-state{background:#166534}'
            .$selector.' .exhibitions-random-artwork-body{display:grid;gap:9px;padding:16px}'
            .$selector.' .exhibitions-random-artwork-meta{display:flex;justify-content:space-between;gap:12px;color:#64748b;font:600 12px/1.4 system-ui,sans-serif}'
            .$selector.' h3{margin:0;font:700 1.05rem/1.45 system-ui,sans-serif}'
            .$selector.' h3 a{color:inherit;text-decoration:none}'
            .$selector.' .exhibitions-random-artwork-artist{margin:0;color:#64748b;font:400 14px/1.5 system-ui,sans-serif}'
            .$selector.' .exhibitions-random-artwork-price{font:700 14px/1.4 system-ui,sans-serif}'
            .$hiddenRule
            .'@media(max-width:991px){'.$selector.' .exhibitions-random-artworks-grid{grid-template-columns:repeat('.$layout['tablet'].',minmax(0,1fr))}}'
            .'@media(max-width:575px){'.$selector.' .exhibitions-random-artworks-grid{grid-template-columns:repeat('.$layout['mobile'].',minmax(0,1fr))}}'
            .'</style>';
    }

    /** @return array<string, string> */
    private function slotAttributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all('/\b(data-exhibitions-[a-z0-9-]+)\s*=\s*(["\'])(.*?)\2/is', (string) ($opening[1] ?? ''), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [
            strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])->all();
    }

    private function instanceKey(array $attributes): string
    {
        $candidate = strtolower((string) ($attributes['data-exhibitions-artworks-instance'] ?? ''));
        $candidate = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $candidate), '-');

        return $candidate !== ''
            ? substr($candidate, 0, 80)
            : 'exhibitions-'.substr(sha1((string) json_encode($attributes)), 0, 12);
    }

    private function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        return $this->ready = Route::has('exhibitions.artworks.show')
            && Route::has('exhibitions.artworks.media')
            && collect([
                'exhibition_artworks',
                'exhibition_artwork_publications',
                'exhibition_artwork_pricing',
                'exhibition_artist_identities',
                'exhibition_artist_applications',
                'exhibition_artwork_inventory_items',
                'exhibition_artwork_media',
            ])->every(fn (string $table): bool => $this->hasTable($table));
    }

    private function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columns[$table.'.'.$column] ??= Schema::hasColumn($table, $column);
    }
}
