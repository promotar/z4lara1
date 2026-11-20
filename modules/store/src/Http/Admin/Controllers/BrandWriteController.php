<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Store\Application\Catalog\BrandCatalogService;
use Modules\Store\Application\Catalog\Brands\BrandDisplayOrderService;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class BrandWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function store(
        Request $request,
        BrandCatalogService $brands,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{0,254}$/', 'unique:store_brands,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,hidden'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $brands->create($data, $request->file('logo'), $this->actor($request));

            return back()->with('success', 'Brand created.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function update(
        Request $request,
        string $brand,
        BrandCatalogService $brands,
        BrandDisplayOrderService $displayOrder,
    ): RedirectResponse {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{0,254}$/', Rule::unique('store_brands', 'slug')->ignore($brand, 'public_id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,hidden'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'display_position' => ['nullable', 'integer', 'min:1'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        try {
            $brands->update(
                $brand,
                $data,
                $request->file('logo'),
                $request->boolean('remove_logo'),
                $this->actor($request),
            );
            if (isset($data['display_position'])) {
                $displayOrder->move($brand, (int) $data['display_position']);
            }

            return back()->with('success', 'Brand updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function archive(Request $request, string $brand, BrandCatalogService $brands): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        try {
            $brands->archive($brand, (int) $data['version'], $this->actor($request));

            return back()->with('success', 'Brand removed safely. Linked products were kept and are now unbranded.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
