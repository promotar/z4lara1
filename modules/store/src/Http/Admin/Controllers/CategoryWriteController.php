<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Application\Catalog\Categories\CategoryDisplayOrderService;
use Modules\Store\Application\Catalog\Categories\CreateCategoryCommand;
use Modules\Store\Application\Catalog\Categories\CreateCategoryHandler;
use Modules\Store\Application\Catalog\Categories\UpdateCategoryCommand;
use Modules\Store\Application\Catalog\Categories\UpdateCategoryHandler;
use Modules\Store\Application\Catalog\CategoryImageService;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class CategoryWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function store(Request $request, CreateCategoryHandler $handler, CategoryImageService $images): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', 'max:16'], 'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255'], 'parent' => ['nullable', 'string', 'size:26'], 'position' => ['nullable', 'integer', 'min:0'], 'catalog_section' => ['nullable', 'in:standard,clothing'], 'description' => ['nullable', 'string', 'max:5000'], 'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        try {
            $category = $handler->handle(new CreateCategoryCommand($data['locale'], $data['name'], $data['slug'], $this->actor($request), $data['parent'] ?? null, null, (int) ($data['position'] ?? 0), $data['description'] ?? null, $data['catalog_section'] ?? null));
            if ($request->hasFile('image')) {
                $images->update($category, $request->file('image'), false);
            }
            if ($request->expectsJson()) {
                return response()->json(['category' => ['public_id' => $category, 'name' => $data['name'], 'parent' => $data['parent'] ?? null]], 201);
            }

            return back()->with('success', 'Category created.');
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return $this->failure($exception);
        }
    }

    public function update(
        Request $request,
        string $category,
        UpdateCategoryHandler $handler,
        CategoryDisplayOrderService $displayOrder,
    ): RedirectResponse {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'locale' => ['required', 'string', 'max:16'], 'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255'], 'status' => ['required', 'in:active,inactive,archived'], 'position' => ['required', 'integer', 'min:0'], 'display_position' => ['nullable', 'integer', 'min:1'], 'featured' => ['nullable', 'boolean'], 'catalog_section' => ['nullable', 'in:standard,clothing'], 'description' => ['nullable', 'string', 'max:5000'], 'parent' => ['nullable', 'string', 'size:26']]);
        try {
            $handler->handle(new UpdateCategoryCommand($category, (int) $data['version'], $data['locale'], $data['name'], $data['slug'], $data['status'], (int) $data['position'], $request->boolean('featured'), $this->actor($request), $data['description'] ?? null, $data['parent'] ?? null, $data['catalog_section'] ?? null));
            if (isset($data['display_position'])) {
                $displayOrder->move($category, (int) $data['display_position']);
            }

            return back()->with('success', 'Category updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function image(
        Request $request,
        string $category,
        CategoryImageService $images,
    ): RedirectResponse {
        $data = $request->validate([
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
        try {
            $images->update($category, $request->file('image'), $request->boolean('remove_image'));

            return back()->with('success', $request->hasFile('image') ? 'Category image updated.' : 'Category image removed.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
