<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Application\Shipping\ShippingRegionCatalog;

final class ShippingZoneAdminController extends Controller
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly ShippingRegionCatalog $regions,
        private readonly StoreSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = $this->database->table('store_shipping_zones')->count();
        $items = $this->database->table('store_shipping_zones as zone')
            ->leftJoin('store_shipping_zone_locations as location', 'location.shipping_zone_id', '=', 'zone.id')
            ->groupBy([
                'zone.id', 'zone.public_id', 'zone.name', 'zone.delivery_cost_minor',
                'zone.free_shipping_threshold_minor', 'zone.estimated_delivery', 'zone.status',
                'zone.priority', 'zone.version', 'zone.created_at',
            ])
            ->orderByDesc('zone.priority')
            ->orderBy('zone.id')
            ->forPage($page, 25)
            ->get([
                'zone.id',
                'zone.public_id',
                'zone.name',
                'zone.delivery_cost_minor',
                'zone.free_shipping_threshold_minor',
                'zone.estimated_delivery',
                'zone.status',
                'zone.priority',
                'zone.version',
                'zone.created_at',
                $this->database->raw('COUNT(location.id) as location_count'),
            ]);

        $zones = new \Illuminate\Pagination\LengthAwarePaginator($items, $total, 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('store::admin.shipping-zones.index', ['zones' => $zones]);
    }

    public function create(): View
    {
        return view('store::admin.shipping-zones.form', [
            'zone' => null,
            'locations' => collect(),
        ] + $this->regionOptions());
    }

    public function edit(string $zone): View
    {
        $record = $this->database->table('store_shipping_zones')->where('public_id', $zone)->first();
        abort_if($record === null, 404);
        $locations = $this->database->table('store_shipping_zone_locations')
            ->where('shipping_zone_id', $record->id)
            ->orderBy('match_type')
            ->orderBy('match_value')
            ->get(['match_type', 'match_value']);

        return view('store::admin.shipping-zones.form', [
            'zone' => $record,
            'locations' => $locations,
        ] + $this->regionOptions());
    }

    /** @return array{countries: array<string, string>, governoratesByCountry: array<string, list<string>>} */
    private function regionOptions(): array
    {
        $enabled = array_fill_keys((array) $this->settings->current()['shipping_countries'], true);
        $countries = array_intersect_key($this->regions->countries(), $enabled);

        return [
            'countries' => $countries,
            'governoratesByCountry' => array_intersect_key($this->regions->governoratesByCountry(), $countries),
        ];
    }
}
