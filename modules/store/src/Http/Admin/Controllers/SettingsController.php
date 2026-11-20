<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Application\Shipping\ShippingRegionCatalog;
use Modules\Store\Contracts\Readiness\StoreReadiness;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class SettingsController extends Controller
{
    use HandlesStoreAdminRequests;

    public function __construct(
        private readonly StoreReadiness $readiness,
        private readonly StoreSettingsService $settings,
        private readonly ShippingRegionCatalog $shippingRegions,
    ) {}

    public function index(): View
    {
        return $this->page('general');
    }

    public function currencyTax(): View
    {
        return $this->page('currency-tax');
    }

    public function pages(): View
    {
        return $this->page('pages');
    }

    public function inventory(): View
    {
        return $this->page('inventory');
    }

    public function orders(): View
    {
        return $this->page('orders');
    }

    public function shippingCountries(): View
    {
        return $this->page('shipping-countries');
    }

    public function cashOnDelivery(): View
    {
        return $this->page('cash-on-delivery');
    }

    public function notifications(): View
    {
        return $this->page('notifications');
    }

    public function policies(): View
    {
        return $this->page('policies');
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->filled('section')) {
            return $this->updateSection($request, $this->settings);
        }

        $data = $request->validate([
            'currency' => ['required', 'in:JOD,USD'],
            'price_display' => ['required', 'in:code,symbol'],
            'default_product_visibility' => ['required', 'in:public,hidden'],
            'default_safety_stock' => ['required', 'integer', 'between:0,1000000'],
            'maximum_upload_mb' => ['required', 'integer', 'between:1,15'],
            'cod_enabled' => ['nullable', 'boolean'],
        ]);
        try {
            $data['cod_enabled'] = $request->boolean('cod_enabled');
            $this->settings->update($data, $this->actor($request));

            return back()->with('success', __('store::ui.settings_saved_cod'));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function page(string $activeSettingsPage): View
    {
        return view('store::admin.settings', [
            'store' => $this->readiness->status(),
            'orders' => (array) config('store.orders', []),
            'settings' => $this->settings->current(),
            'notificationVariables' => ['customer_name', 'order_number', 'order_status', 'store_name'],
            'shippingCountries' => $this->shippingRegions->countries(),
            'shippingCountriesByContinent' => $this->shippingRegions->countriesByContinent(),
            'storeCountryTimezones' => $this->shippingRegions->timezonesByCountry(),
            'storeCountryTimezoneLabels' => $this->shippingRegions->timezoneLabelsByCountry(),
            'activeSettingsPage' => $activeSettingsPage,
        ]);
    }

    private function updateSection(Request $request, StoreSettingsService $settings): RedirectResponse
    {
        $section = $request->validate([
            'section' => ['required', 'in:general,shipping,currency,tax,inventory,orders,cod,customer_notifications,admin_notifications,policies,pages'],
        ])['section'];

        $values = $request->except(['_token', '_method', 'section']);
        foreach ($this->checkboxes($section) as $key) {
            data_set($values, $key, $request->boolean($key));
        }

        if ($section === 'tax') {
            $values['tax_basis_points'] = (int) round(((float) $request->input('tax_percent', 0)) * 100);
        }
        if ($section === 'orders') {
            $values['minimum_order_minor'] = (int) round(((float) $request->input('minimum_order', 0)) * 1000);
        }

        try {
            $settings->updateSection($section, $values, $this->actor($request));

            return back()->with('success', __('store::ui.settings_saved'));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    /** @return list<string> */
    private function checkboxes(string $section): array
    {
        return match ($section) {
            'general' => ['store_accepting_orders'],
            'tax' => ['prices_include_tax'],
            'inventory' => [
                'inventory_enabled',
                'cancelled_orders_restore_stock',
                'returned_products_restore_stock',
                'admin_low_stock_notification',
                'show_stock_status',
            ],
            'orders' => ['coupon_release_on_cancel', 'store_accepting_orders'],
            'cod' => ['cod_enabled', 'cod_allow_collection'],
            'customer_notifications' => array_map(
                static fn (string $status): string => "{$status}.enabled",
                StoreSettingsService::ORDER_STATUSES,
            ),
            'admin_notifications' => ['new_order', 'cancellation_request', 'low_stock', 'customer_email_failed'],
            default => [],
        };
    }
}
