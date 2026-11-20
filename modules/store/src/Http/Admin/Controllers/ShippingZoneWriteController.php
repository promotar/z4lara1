<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Application\Shipping\ShippingRegionCatalog;
use Modules\Store\Application\Shipping\ShippingZoneManagementService;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class ShippingZoneWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function __construct(
        private readonly ShippingRegionCatalog $regions,
        private readonly StoreSettingsService $settings,
    ) {}

    public function store(Request $request, ShippingZoneManagementService $zones): RedirectResponse
    {
        try {
            $publicId = $zones->create($this->data($request), (int) $request->user()->id);

            return redirect()->route('admin.plugins.store.shipping-zones.edit', $publicId)
                ->with('success', 'Shipping zone created successfully.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function update(
        Request $request,
        string $zone,
        ShippingZoneManagementService $zones,
    ): RedirectResponse {
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        try {
            $zones->update($zone, (int) $version, $this->data($request), (int) $request->user()->id);

            return back()->with('success', 'Shipping zone updated. Existing orders were not changed.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function status(
        Request $request,
        string $zone,
        ShippingZoneManagementService $zones,
    ): RedirectResponse {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ]);
        try {
            $zones->setActive(
                $zone,
                (int) $data['version'],
                (bool) $data['active'],
                (int) $request->user()->id,
            );

            return back()->with('success', (bool) $data['active'] ? 'Shipping zone activated.' : 'Shipping zone disabled.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function destroy(
        Request $request,
        string $zone,
        ShippingZoneManagementService $zones,
    ): RedirectResponse {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'zone_name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $zones->delete($zone, (int) $data['version'], (string) $data['zone_name']);

            return redirect()->route('admin.plugins.store.shipping-zones.index')
                ->with('success', 'Shipping zone deleted permanently.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        $request->merge([
            'coverage_type' => $request->input('coverage_type', 'country'),
        ]);
        $enabledCountries = array_values(array_intersect(
            array_keys($this->regions->countries()),
            (array) $this->settings->current()['shipping_countries'],
        ));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'coverage_type' => ['required', 'string', Rule::in(['country', 'rest_of_world'])],
            'delivery_cost' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'free_shipping_threshold' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'priority' => ['required', 'integer', 'between:-10000,10000'],
            'active' => ['nullable', 'boolean'],
            'country' => [
                $request->input('coverage_type') === 'rest_of_world' ? 'nullable' : 'required',
                'string',
                Rule::in($enabledCountries),
            ],
            'governorate' => [
                'nullable',
                'string',
                Rule::in($this->regions->governorates((string) $request->input('country'))),
            ],
        ]);
        $currency = (string) config('store.default_currency', 'JOD');

        return [
            'name' => $data['name'],
            'delivery_cost_minor' => Money::fromDecimal($data['delivery_cost'], $currency)->amountMinor,
            'free_shipping_threshold_minor' => empty($data['free_shipping_threshold'])
                ? null
                : Money::fromDecimal($data['free_shipping_threshold'], $currency)->amountMinor,
            'priority' => (int) $data['priority'],
            'active' => $request->boolean('active'),
            'locations' => $data['coverage_type'] === 'rest_of_world'
                ? ['country' => ['*']]
                : [
                    'country' => [(string) $data['country']],
                    'governorate' => empty($data['governorate']) ? [] : [(string) $data['governorate']],
                ],
        ];
    }
}
