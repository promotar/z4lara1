<?php

namespace Modules\Blog\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Blog\Models\Tag;

class TagController extends Controller
{
    public function index(): View
    {
        return view('blog::admin.tags.index', ['tags' => Tag::query()->withCount('posts')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tag::query()->create($this->attributes($request));

        return $this->relativeRedirect('/admin/plugins/blog/tags', 'Tag created.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $tag->update($this->attributes($request, $tag));

        return $this->relativeRedirect('/admin/plugins/blog/tags#tag-'.$tag->getKey(), 'Tag updated.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return $this->relativeRedirect('/admin/plugins/blog/tags', 'Tag deleted.');
    }

    /** @return array<string, string> */
    private function attributes(Request $request, ?Tag $tag = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_tags', 'slug')->ignore($tag?->id)],
        ]);
        $validated['slug'] = ($validated['slug'] ?? '') ?: $this->uniqueSlug($validated['name'], $tag?->id);

        return $validated;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $arabic = ['ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'ة' => 'a', 'و' => 'w', 'ؤ' => 'w', 'ي' => 'y', 'ى' => 'a', 'ئ' => 'y'];
        $base = Str::slug(strtr($value, $arabic)) ?: 'tag';
        $slug = $base;
        $index = 2;

        while (Tag::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
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
