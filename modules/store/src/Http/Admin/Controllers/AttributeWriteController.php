<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Application\Catalog\ProductAttributeService;
use Modules\Store\Domain\Catalog\AttributeDataType;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class AttributeWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function store(Request $request, ProductAttributeService $service): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{0,127}$/'], 'data_type' => ['required', 'in:text,integer,decimal,boolean,select'], 'locale' => ['required', 'string', 'max:16'], 'name' => ['required', 'string', 'max:255'], 'filterable' => ['nullable', 'boolean'], 'visible' => ['nullable', 'boolean'], 'supports_variant_axis' => ['nullable', 'boolean']]);
        try {
            $service->createDefinition($data['code'], AttributeDataType::from($data['data_type']), $data['locale'], $data['name'], $request->boolean('filterable'), $request->boolean('visible'), $request->boolean('supports_variant_axis'));

            return back()->with('success', 'Attribute created.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function addOption(Request $request, string $attribute, ProductAttributeService $service): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{0,127}$/'], 'locale' => ['required', 'string', 'max:16'], 'name' => ['required', 'string', 'max:255'], 'position' => ['nullable', 'integer', 'min:0']]);
        try {
            $service->addOption($attribute, $data['code'], $data['locale'], $data['name'], (int) ($data['position'] ?? 0));

            return back()->with('success', 'Attribute option added.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function status(Request $request, string $attribute, ProductAttributeService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,inactive,archived']]);
        try {
            $service->setDefinitionStatus($attribute, $data['status']);

            return back()->with('success', 'Attribute status updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function optionStatus(Request $request, string $attribute, string $option, ProductAttributeService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,inactive,archived']]);
        try {
            $service->setOptionStatus($attribute, $option, $data['status']);

            return back()->with('success', 'Option status updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
