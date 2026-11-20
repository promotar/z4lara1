<?php

namespace Modules\Exhibition\Http\PublicSite\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Exhibition\Application\Artworks\ArtworkTranslationService;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicArtworkController extends Controller
{
    public function __construct(
        private readonly ArtworkCommerceGateway $commerce,
        private readonly ExhibitionSettingsService $settings,
        private readonly ArtworkEffectiveAvailabilityService $availability,
        private readonly ArtworkTranslationService $translations,
    ) {}

    public function index(Request $request): View
    {
        $this->assertPublicPagesEnabled();
        $search = trim($request->string('search')->toString());
        $category = trim($request->string('category')->toString());
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
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.$search.'%';
                $query->where(function ($query) use ($needle): void {
                    $query->where('artwork.title', 'like', $needle)
                        ->orWhere('artwork.category', 'like', $needle)
                        ->orWhere('artwork.subject', 'like', $needle)
                        ->orWhere('application.artistic_name', 'like', $needle)
                        ->orWhere('application.legal_name', 'like', $needle);
                });
            })
            ->when($category !== '', fn ($query) => $query->where('artwork.category', $category))
            ->select(
                'artwork.id',
                'artwork.public_id',
                'artwork.title',
                'artwork.category',
                'artwork.technique',
                'artwork.material',
                'artwork.short_description',
                'artwork.completion_year',
                'artwork.edition_type',
                'pricing.approved_amount_snapshot',
                'pricing.currency',
                'application.artistic_name',
                'application.legal_name',
            )
            ->when(Schema::hasColumn('exhibition_artworks', 'slug'), fn ($query) => $query->addSelect('artwork.slug'))
            ->orderByDesc('artwork.published_at')
            ->orderByDesc('artwork.id');

        $candidates = $query->get();
        $catalogArtworks = $candidates
            ->filter(function (object $artwork): bool {
                $commerce = $this->commerce->status((string) $artwork->public_id);
                $effectiveAvailability = $this->availability->forArtwork(
                    (int) $artwork->id,
                    $commerce->availability,
                );
                // Visitor-facing catalog semantics: an accepted order removes
                // the last available unit immediately. Keep the artwork public
                // and label it sold until Store cancellation releases it.
                $artwork->is_sold = in_array($effectiveAvailability, ['reserved', 'sold'], true);

                return $artwork->is_sold || $commerce->purchasable;
            })
            ->values();
        $page = max(1, $request->integer('page', 1));
        $artworks = new LengthAwarePaginator(
            $catalogArtworks->forPage($page, 12)->values(),
            $catalogArtworks->count(),
            12,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $artworkIds = collect($artworks->items())->pluck('id');
        if ($this->translations->available() && $artworkIds->isNotEmpty()) {
            $translationRows = DB::table('exhibition_artwork_translations')
                ->whereIn('artwork_id', $artworkIds)
                ->where('locale', app()->getLocale())
                ->get()
                ->keyBy('artwork_id');

            foreach ($artworks->items() as $artworkItem) {
                if (isset($translationRows[$artworkItem->id])) {
                    $this->translations->apply($artworkItem, $translationRows[$artworkItem->id]);
                }
            }
        }

        $images = DB::table('exhibition_artwork_media')
            ->whereIn('artwork_id', collect($artworks->items())->pluck('id'))
            ->where('state', 'ready')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('artwork_id');

        foreach ($artworks->items() as $artwork) {
            $artwork->primary_media = $images->get($artwork->id)?->first();
        }

        $categories = DB::table('exhibition_artworks')
            ->where('state', 'published')
            ->where(function ($query): void {
                $query->whereExists(fn ($inventory) => $inventory
                    ->selectRaw('1')
                    ->from('exhibition_artwork_inventory_items as available_inventory')
                    ->whereColumn('available_inventory.artwork_id', 'exhibition_artworks.id')
                    ->where('available_inventory.state', 'available'))
                    ->orWhere(function ($query): void {
                        $query->whereExists(fn ($inventory) => $inventory
                            ->selectRaw('1')
                            ->from('exhibition_artwork_inventory_items as unavailable_sale_inventory')
                            ->whereColumn('unavailable_sale_inventory.artwork_id', 'exhibition_artworks.id')
                            ->whereIn('unavailable_sale_inventory.state', ['reserved', 'sold']))
                            ->whereNotExists(fn ($inventory) => $inventory
                                ->selectRaw('1')
                                ->from('exhibition_artwork_inventory_items as remaining_inventory')
                                ->whereColumn('remaining_inventory.artwork_id', 'exhibition_artworks.id')
                                ->where('remaining_inventory.state', 'available'));
                    })->orWhere(function ($query): void {
                        $this->availability->constrainToConfirmedSale($query, 'exhibition_artworks.id');
                    });
            })
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('exhibitions::public.artworks-index', compact(
            'artworks',
            'categories',
            'search',
            'category',
        ));
    }

    public function show(Request $request, string $artwork): View|RedirectResponse
    {
        $this->assertPublicPagesEnabled();
        $row = $this->published($artwork);
        $row = $this->translations->apply($row, $this->translations->resolve((int) $row->id, app()->getLocale()));
        $permalink = $this->permalink($row);
        if ($artwork !== $permalink) {
            return redirect()->route('exhibitions.artworks.show', $permalink, 301);
        }
        $media = DB::table('exhibition_artwork_media')->where('artwork_id', $row->id)->where('state', 'ready')->orderByDesc('is_primary')->orderBy('sort_order')->get();
        $artist = DB::table('exhibition_artist_identities as identity')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('identity.id', $row->artist_identity_id)
            ->select('application.artistic_name', 'application.legal_name', 'application.biography')
            ->orderByDesc('application.updated_at')->first() ?? abort(404);
        $publication = DB::table('exhibition_artwork_publications')->where('artwork_id', $row->id)->where('state', 'published')->first() ?? abort(404);
        $authenticity = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $row->id)->first();
        $commerce = $this->commerce->status($row->public_id);
        $inventoryStates = DB::table('exhibition_artwork_inventory_items')
            ->where('artwork_id', $row->id)
            ->pluck('state');
        $effectiveAvailability = $this->availability->forArtwork((int) $row->id, $commerce->availability);
        $availability = match ($effectiveAvailability) {
            'sold' => 'Sold',
            // Once the only unit belongs to an active order, visitors see it
            // as sold. A Store cancellation releases the unit and this becomes
            // Available again without manual Exhibitions work.
            'reserved' => 'Sold',
            'available' => $commerce->purchasable ? 'Available' : 'Unavailable',
            default => 'Unavailable',
        };
        $canAddToCart = $commerce->storeAvailable
            && $commerce->purchasable
            && $inventoryStates->contains('available')
            && $commerce->maximumQuantity === 1
            && filled($publication->store_variant_public_id)
            && Route::has('store.cart.items.store');

        return view('exhibitions::public.artwork', [
            'artwork' => $row,
            'media' => $media,
            'artist' => $artist,
            'commerce' => $commerce,
            'publication' => $publication,
            'storeUrl' => $this->storeUrl((string) $publication->store_product_public_id),
            'availability' => $availability,
            'canAddToCart' => $canAddToCart,
            'authenticity' => $authenticity,
            'related' => $this->related($row),
        ]);
    }

    public function media(string $artwork, string $media): StreamedResponse
    {
        $this->assertPublicPagesEnabled();
        $row = $this->published($artwork);
        $item = DB::table('exhibition_artwork_media')->where('artwork_id', $row->id)->where('public_id', $media)->where('state', 'ready')->first() ?? abort(404);
        abort_unless($item->display_path && Storage::disk($item->disk)->exists($item->display_path), 404);

        return Storage::disk($item->disk)->response($item->display_path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function inquire(Request $request, string $artwork): RedirectResponse
    {
        $this->assertPublicPagesEnabled();
        $row = $this->published($artwork);
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:4000']]);
        DB::table('exhibition_artwork_inquiries')->insert(['public_id' => (string) Str::uuid(), 'artwork_id' => $row->id, 'customer_user_id' => $request->user()->id, 'message' => $data['message'], 'state' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Your inquiry was sent to the platform team.');
    }

    private function assertPublicPagesEnabled(): void
    {
        abort_unless((bool) $this->settings->value('exhibitions.public_artwork_pages.enabled', true), 404);
    }

    private function published(string $public): object
    {
        $query = DB::table('exhibition_artworks')->where('state', 'published');
        $row = (clone $query)
            ->where(function ($query) use ($public): void {
                $query->where('public_id', $public);
                if (Schema::hasColumn('exhibition_artworks', 'slug')) {
                    $query->orWhere('slug', $public);
                }
            })
            ->first();

        if ($row === null) {
            $row = (clone $query)
                ->get()
                ->first(fn (object $candidate): bool => $this->permalink($candidate) === $public);
        }

        return $row ?? abort(404);
    }

    private function permalink(object $artwork): string
    {
        return filled($artwork->slug ?? null)
            ? (string) $artwork->slug
            : (Str::slug((string) ($artwork->title ?? '')) ?: (string) $artwork->public_id);
    }

    private function related(object $artwork)
    {
        $rows = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artwork_publications as publication', function ($join): void {
                $join->on('publication.artwork_id', '=', 'artwork.id')->where('publication.state', 'published');
            })
            ->join('exhibition_artwork_pricing as pricing', function ($join): void {
                $join->on('pricing.artwork_id', '=', 'artwork.id')->where('pricing.state', 'approved');
            })
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('artwork.state', 'published')->where('artwork.id', '!=', $artwork->id)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('exhibition_artwork_inventory_items as inventory')->whereColumn('inventory.artwork_id', 'artwork.id')->where('inventory.state', 'available'))
            ->select('artwork.id', 'artwork.public_id', 'artwork.title', 'artwork.short_description', 'pricing.approved_amount_snapshot', 'pricing.currency', 'application.artistic_name', 'application.legal_name')
            ->when(Schema::hasColumn('exhibition_artworks', 'slug'), fn ($query) => $query->addSelect('artwork.slug'))
            ->orderByRaw('case when artwork.category = ? then 0 else 1 end', [$artwork->category])
            ->orderByDesc('artwork.published_at')->distinct()->limit(3)->get();
        if ($rows->isEmpty()) return $rows;
        $images = DB::table('exhibition_artwork_media')->whereIn('artwork_id', $rows->pluck('id'))->where('state', 'ready')->orderByDesc('is_primary')->orderBy('sort_order')->get()->groupBy('artwork_id');
        foreach ($rows as $related) $related->primary_media = $images->get($related->id)?->first();

        return $rows;
    }

    private function storeUrl(string $productPublicId): ?string
    {
        $contract = 'Modules\\Store\\Contracts\\Catalog\\ProductReadService';
        $contextClass = 'Modules\\Store\\Contracts\\Catalog\\ProductReadContext';
        if ($productPublicId === '' || ! interface_exists($contract) || ! class_exists($contextClass) || ! app()->bound($contract)) return null;
        $context = new $contextClass('en', 'en', 'JOD', now()->toDateTimeImmutable());
        $product = app($contract)->findByPublicId($productPublicId, $context);

        return $product ? url('/store/products/'.$product->slug) : null;
    }
}
