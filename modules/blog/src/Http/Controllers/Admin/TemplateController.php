<?php

namespace Modules\Blog\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Blog\Models\Media;
use Modules\Blog\Models\Template;

class TemplateController extends Controller
{
    public function index(): View
    {
        return $this->editor(new Template([
            'category' => 'single',
            'status' => 'active',
            'html_code' => $this->starterHtml(),
            'css_code' => $this->starterCss(),
        ]));
    }

    public function edit(Template $template): View
    {
        $template->load('previewImage');

        return $this->editor($template);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request);
        $attributes['created_by'] = $request->user()?->id;
        $attributes['updated_by'] = $request->user()?->id;

        $template = Template::query()->create($attributes);
        event('blog.template.saved', [$template, 'created']);

        return redirect(route('admin.plugins.blog.templates.edit', $template, false))
            ->with('status', 'Template created.');
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403, 'System templates are read-only. Duplicate this template to customize it.');
        $attributes = $this->validatedAttributes($request, $template);
        $attributes['updated_by'] = $request->user()?->id;
        $template->update($attributes);
        event('blog.template.saved', [$template->fresh(), 'updated']);

        return redirect(route('admin.plugins.blog.templates.edit', $template, false))
            ->with('status', 'Template saved.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403, 'System templates cannot be deleted.');
        $template->delete();

        return redirect(route('admin.plugins.blog.templates.index', [], false))
            ->with('status', 'Template deleted.');
    }

    public function duplicate(Request $request, Template $template): RedirectResponse
    {
        $copy = $template->replicate(['is_system', 'system_key', 'created_by', 'updated_by']);
        $copy->name = $template->name.' Copy';
        $copy->slug = $this->uniqueSlug($template->slug.'-copy');
        $copy->status = 'draft';
        $copy->is_system = false;
        $copy->system_key = null;
        $copy->created_by = $request->user()?->id;
        $copy->updated_by = $request->user()?->id;
        $copy->save();

        return redirect(route('admin.plugins.blog.templates.edit', $copy, false))
            ->with('status', 'Template copied. Review it and set it to Active when ready.');
    }

    public function catalog(): JsonResponse
    {
        $templates = Template::query()
            ->active()
            ->with('previewImage')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Template $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'category' => $template->category,
                'html' => $template->html_code ?: '',
                'css' => $template->css_code ?: '',
                'js' => $template->js_code ?: '',
                'preview_image' => $template->previewImageUrl(),
                'updated_at' => $template->updated_at?->toIso8601String(),
                'is_system' => $template->isSystem(),
            ])
            ->values();

        return response()->json([
            'items' => $templates,
            'syntax' => [
                'collection' => '{{#posts}} ... {{/posts}}',
                'tag_collection' => '{{#tags}} ... {{/tags}}',
                'category_collection' => '{{#categories}} ... {{/categories}}',
                'groups' => $this->tokenReference(),
            ],
        ]);
    }

    private function editor(Template $template): View
    {
        return view('blog::admin.templates.editor', [
            'template' => $template,
            'templates' => Template::query()->with('previewImage')->latest('updated_at')->get(),
            'categories' => [
                'single' => 'Single Post',
                'archive' => 'Archive',
                'category' => 'Category Archive',
                'search' => 'Search Results',
                'card' => 'Post Card',
                'slider' => 'Post Slider',
                'list' => 'Post List',
                'hero' => 'Post Hero',
                'custom' => 'Custom',
            ],
            'tokenGroups' => $this->tokenReference(),
            'isSystem' => $template->isSystem(),
        ]);
    }

    /** @return array<string, array<int, array{token: string, description: string}>> */
    private function tokenReference(): array
    {
        return [
            'Post content' => [
                ['token' => '{{id}}', 'description' => 'Post database ID.'],
                ['token' => '{{title}}', 'description' => 'Post title.'],
                ['token' => '{{slug}}', 'description' => 'Post slug.'],
                ['token' => '{{url}}', 'description' => 'Public post URL.'],
                ['token' => '{{excerpt}}', 'description' => 'Saved excerpt or an automatic content summary.'],
                ['token' => '{{content_text}}', 'description' => 'Post content converted to safe plain text.'],
                ['token' => '{{{content}}}', 'description' => 'Saved sanitized post HTML. Use only where full article content is required.'],
                ['token' => '{{status}}', 'description' => 'Publishing status.'],
                ['token' => '{{visibility}}', 'description' => 'Public, private, or password visibility label.'],
                ['token' => '{{template}}', 'description' => 'Template key saved on the post.'],
                ['token' => '{{layout}}', 'description' => 'Layout key saved on the post.'],
            ],
            'Image and taxonomy' => [
                ['token' => '{{featured_image}}', 'description' => 'Featured image URL.'],
                ['token' => '{{featured_image_alt}}', 'description' => 'Featured image alternative text.'],
                ['token' => '{{category}}', 'description' => 'Primary category name.'],
                ['token' => '{{category_slug}}', 'description' => 'Primary category slug.'],
                ['token' => '{{category_url}}', 'description' => 'Primary category archive URL.'],
                ['token' => '{{categories}}', 'description' => 'All category names separated by commas.'],
                ['token' => '{{tags}}', 'description' => 'All tag names separated by commas.'],
                ['token' => '{{#categories}}...{{/categories}}', 'description' => 'Repeat markup for every category. Inside use {{name}}, {{slug}}, and {{url}}.'],
                ['token' => '{{#tags}}...{{/tags}}', 'description' => 'Repeat markup for every tag. Inside use {{name}}, {{slug}}, and {{url}}.'],
            ],
            'Author and dates' => [
                ['token' => '{{author}}', 'description' => 'Post author display name.'],
                ['token' => '{{author_id}}', 'description' => 'Post author ID.'],
                ['token' => '{{published_at}}', 'description' => 'Human-readable publishing date.'],
                ['token' => '{{published_at_iso}}', 'description' => 'ISO 8601 publishing timestamp.'],
                ['token' => '{{created_at}}', 'description' => 'Human-readable creation date.'],
                ['token' => '{{updated_at}}', 'description' => 'Human-readable last update date.'],
            ],
            'SEO' => [
                ['token' => '{{seo_title}}', 'description' => 'SEO title, falling back to the post title.'],
                ['token' => '{{seo_description}}', 'description' => 'SEO meta description.'],
                ['token' => '{{focus_keyword}}', 'description' => 'SEO focus keyword.'],
                ['token' => '{{canonical_url}}', 'description' => 'Canonical URL.'],
                ['token' => '{{robots_index}}', 'description' => 'index or noindex.'],
                ['token' => '{{robots_follow}}', 'description' => 'follow or nofollow.'],
                ['token' => '{{schema_type}}', 'description' => 'Saved schema type.'],
                ['token' => '{{seo_score}}', 'description' => 'Calculated SEO score from 0 to 100.'],
                ['token' => '{{seo_social_title}}', 'description' => 'Saved social sharing title.'],
                ['token' => '{{seo_social_description}}', 'description' => 'Saved social sharing description.'],
            ],
            'Archive and site' => [
                ['token' => '{{#posts}}...{{/posts}}', 'description' => 'Repeat markup for every post in the supplied result.'],
                ['token' => '{{site_name}}', 'description' => 'Platform name.'],
                ['token' => '{{archive_title}}', 'description' => 'Current archive, category, tag, or search title.'],
                ['token' => '{{results_count}}', 'description' => 'Number of posts supplied to the template.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, ?Template $template = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('blog_templates', 'slug')->ignore($template?->id)],
            'category' => ['required', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'draft'])],
            'html_code' => ['nullable', 'string', 'max:1000000'],
            'css_code' => ['nullable', 'string', 'max:1000000'],
            'js_code' => ['nullable', 'string', 'max:1000000'],
            'preview_image_id' => ['nullable', 'integer', 'exists:blog_media,id'],
            'preview_image' => ['nullable', 'string', 'max:2048'],
        ]);

        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['name'], $template?->id);

        if (! empty($validated['preview_image_id'])) {
            $validated['preview_image'] = Media::query()->find($validated['preview_image_id'])?->url
                ?: ($validated['preview_image'] ?? null);
        }

        return $validated;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'template';
        $slug = $base;
        $suffix = 2;

        while (Template::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function starterHtml(): string
    {
        return <<<'HTML'
<article class="post-template-card">
    <img src="https://placehold.co/1200x675/f4e6e6/7f1d1d?text=Featured+Image" alt="Featured image">
    <div class="post-template-content">
        <span class="post-template-category">Art & Culture</span>
        <h2>Sample post title</h2>
        <p>Use this preview area to build a post, archive, search result, card, slider, or any reusable Blog template.</p>
        <a href="#">Read article</a>
    </div>
</article>
HTML;
    }

    private function starterCss(): string
    {
        return <<<'CSS'
.post-template-card {
    overflow: hidden;
    max-width: 760px;
    margin: 32px auto;
    border: 1px solid #eadada;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 18px 45px rgba(45, 18, 18, .1);
    font-family: Arial, sans-serif;
}
.post-template-card img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
.post-template-content { padding: 28px; }
.post-template-category { color: #a90000; font-size: 12px; font-weight: 800; text-transform: uppercase; }
.post-template-content h2 { margin: 10px 0; color: #241b1b; font-size: 30px; }
.post-template-content p { color: #625757; line-height: 1.7; }
.post-template-content a { color: #a90000; font-weight: 800; text-decoration: none; }
CSS;
    }
}
