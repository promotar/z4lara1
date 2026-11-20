<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;

final class ArtworkInventoryAdminController extends Controller
{
    public function __construct(private readonly ArtworkInventoryService $service) {}

    public function index(): View
    {
        $rows = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
            ->leftJoin('exhibition_artwork_inventory_items as inventory', 'inventory.artwork_id', '=', 'artwork.id')
            ->where('publication.state', 'published')
            ->groupBy('artwork.id', 'artwork.public_id', 'artwork.title', 'artwork.edition_type', 'artwork.edition_total')
            ->select('artwork.id', 'artwork.public_id', 'artwork.title', 'artwork.edition_type', 'artwork.edition_total')
            ->selectRaw('COUNT(inventory.id) as unit_count')
            ->selectRaw("SUM(CASE WHEN inventory.state = 'available' THEN 1 ELSE 0 END) as available_count")
            ->selectRaw("SUM(CASE WHEN inventory.state = 'sold' THEN 1 ELSE 0 END) as sold_count")
            ->orderByDesc('artwork.id')->paginate(30);

        return view('exhibitions::admin.inventory.index', compact('rows'));
    }

    public function show(string $artwork): View
    {
        $record = DB::table('exhibition_artworks')->where('public_id', $artwork)->first() ?? abort(404);
        $items = DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $record->id)->orderByRaw('edition_number IS NULL DESC')->orderBy('edition_number')->get();
        $events = DB::table('exhibition_artwork_inventory_events as event')->join('exhibition_artwork_inventory_items as item', 'item.id', '=', 'event.inventory_item_id')->where('item.artwork_id', $record->id)->select('event.*', 'item.inventory_key')->orderByDesc('event.occurred_at')->limit(100)->get();

        return view('exhibitions::admin.inventory.show', ['artwork' => $record, 'items' => $items, 'events' => $events]);
    }

    public function initialize(Request $request, string $artwork): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->run(fn () => $this->service->initialize((int) $request->user()->id, $artwork, $data['reason']), 'Inventory initialized and reconciled with Store.');
    }

    public function override(Request $request, string $item): RedirectResponse
    {
        $data = $request->validate(['state' => ['required', 'in:available,temporarily_held,reserved,sold,unavailable,archived'], 'reason' => ['required', 'string', 'max:2000'], 'idempotency_key' => ['required', 'string', 'max:191']]);

        return $this->run(fn () => $this->service->override((int) $request->user()->id, $item, $data['state'], $data['reason'], $data['idempotency_key']), 'Inventory override recorded and synchronized.');
    }

    public function expire(): RedirectResponse
    {
        $count = $this->service->releaseExpired();

        return back()->with('status', $count.' expired hold(s) released idempotently.');
    }

    private function run(callable $operation, string $message): RedirectResponse
    {
        try {
            $operation();
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['inventory' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
