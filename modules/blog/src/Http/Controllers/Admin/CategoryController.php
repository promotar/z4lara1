<?php

namespace Modules\Blog\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Blog\Models\Category;

class CategoryController extends Controller
{
    public function index(): View
    {
        $view = request()->string('view')->toString() === 'trash' ? 'trash' : 'all';
        $query = Category::query()->when($view === 'trash', fn ($query) => $query->onlyTrashed());

        return view('blog::admin.categories.index', [
            'categories' => $query->with('parent')->withCount('posts')->orderBy('sort_order')->orderBy('name')->get(),
            'parentCategories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'view' => $view,
            'tabCounts' => ['all' => Category::query()->count(), 'trash' => Category::onlyTrashed()->count()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::query()->create($this->attributes($request));

        return $this->relativeRedirect('/admin/plugins/blog/categories', 'Category created.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $category = Category::query()->create($this->attributes($request, quick: true));

        return response()->json(['ok' => true, 'category' => $category->only(['id', 'name', 'slug'])]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->attributes($request, $category));

        return $this->relativeRedirect('/admin/plugins/blog/categories#category-'.$category->getKey(), 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return $this->relativeRedirect('/admin/plugins/blog/categories', 'Category deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'bulk_action' => ['required', 'in:trash,restore,force_delete'],
        ]);
        $categories = Category::withTrashed()->whereKey($validated['ids'])->get();

        match ($validated['bulk_action']) {
            'trash' => $categories->each(fn (Category $category) => $category->trashed() ?: $category->delete()),
            'restore' => $categories->each(fn (Category $category) => $category->trashed() ? $category->restore() : null),
            'force_delete' => $categories->each(fn (Category $category) => $category->trashed() ? $category->forceDelete() : null),
        };

        $path = $validated['bulk_action'] === 'trash' ? '/admin/plugins/blog/categories' : '/admin/plugins/blog/categories?view=trash';

        return $this->relativeRedirect($path, 'Bulk action completed.');
    }

    public function emptyTrash(): RedirectResponse
    {
        Category::onlyTrashed()->get()->each->forceDelete();

        return $this->relativeRedirect('/admin/plugins/blog/categories?view=trash', 'Category trash emptied.');
    }

    /** @return array<string, mixed> */
    private function attributes(Request $request, ?Category $category = null, bool $quick = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'sort_order' => ['nullable', 'integer'],
        ];

        if (! $quick) {
            $rules += [
                'seo_title' => ['nullable', 'string', 'max:255'],
                'seo_description' => ['nullable', 'string', 'max:500'],
                'image' => ['nullable', 'string', 'max:2048'],
                'image_alt' => ['nullable', 'string', 'max:255'],
                'remove_image' => ['nullable', 'boolean'],
            ];
        }

        $validated = $request->validate($rules);
        $validated['slug'] = ($validated['slug'] ?? '') ?: $this->uniqueSlug($validated['name'], $category?->id);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        if (! $quick && $request->boolean('remove_image')) {
            $validated['image'] = null;
            $validated['image_alt'] = null;
        }
        unset($validated['remove_image']);

        return $validated;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = $this->englishSlug($value, 'category');
        $slug = $base;
        $index = 2;

        while (Category::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function englishSlug(string $value, string $fallback): string
    {
        $arabic = ['ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'ة' => 'a', 'و' => 'w', 'ؤ' => 'w', 'ي' => 'y', 'ى' => 'a', 'ئ' => 'y'];

        return Str::slug(strtr($value, $arabic)) ?: $fallback;
    }

    private function relativeRedirect(string $path, ?string $status = null): RedirectResponse
    {
        $response = redirect()->to($path);
        if ($status !== null) {
            $response->with('status', $status);
        }
        $response->headers->set('Location', $path);

        return $response;
    }
}
