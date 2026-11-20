<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;

final readonly class ArtworkInventoryController
{
    public function __construct(private ArtistDashboardService $dashboard) {}

    public function show(Request $request, string $artwork): View
    {
        $record = DB::table('exhibition_artworks as artwork')->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')->where('artwork.public_id', $artwork)->where('identity.core_user_id', (int) $request->user()->id)->select('artwork.*')->first() ?? abort(404);
        $items = DB::table('exhibition_artwork_inventory_items')->where('artwork_id', $record->id)->orderByRaw('edition_number IS NULL DESC')->orderBy('edition_number')->get();
        $overview = $this->dashboard->overview((int) $request->user()->id);

        return view('exhibitions::artist.artworks.inventory', ['artwork' => $record, 'items' => $items, 'context' => $overview['context'], 'navCounts' => $this->dashboard->navigationCounts((int) $request->user()->id), 'activeTab' => 'artworks']);
    }
}
