<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Store\Application\Storefront\StorefrontCatalogService;
use Modules\Store\Models\StoreProductTemplate;

final class StoreProductTemplateController extends Controller
{
    public function __construct(private readonly StorefrontCatalogService $catalog) {}

    public function index(): View
    {
        return $this->editor(new StoreProductTemplate([
            'category' => 'collection',
            'status' => 'draft',
            'html_code' => $this->starterHtml(),
            'css_code' => $this->starterCss(),
            'js_code' => '',
        ]));
    }

    public function edit(StoreProductTemplate $template): View
    {
        return $this->editor($template);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request);
        $attributes['created_by'] = $request->user()?->id;
        $attributes['updated_by'] = $request->user()?->id;

        $template = StoreProductTemplate::query()->create($attributes);

        return redirect(route('admin.plugins.store.templates.edit', $template, false))
            ->with('status', 'Product template created.');
    }

    public function update(Request $request, StoreProductTemplate $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403, 'The protected default template is read-only. Copy it to customize it.');
        $attributes = $this->validatedAttributes($request, $template);
        $attributes['updated_by'] = $request->user()?->id;
        $template->update($attributes);

        return redirect(route('admin.plugins.store.templates.edit', $template, false))
            ->with('status', 'Product template saved.');
    }

    public function destroy(StoreProductTemplate $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403, 'The protected default template cannot be deleted.');
        $template->delete();

        return redirect(route('admin.plugins.store.templates.index', [], false))
            ->with('status', 'Product template deleted.');
    }

    public function duplicate(Request $request, StoreProductTemplate $template): RedirectResponse
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

        return redirect(route('admin.plugins.store.templates.edit', $copy, false))
            ->with('status', 'Template copied as a draft. Review and publish it when ready.');
    }

    public function catalog(): JsonResponse
    {
        return response()->json([
            'items' => StoreProductTemplate::query()
                ->active()
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->map(fn (StoreProductTemplate $template): array => [
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'category' => $template->category,
                    'preview_image' => $template->preview_image,
                    'is_system' => $template->isSystem(),
                    'updated_at' => $template->updated_at?->toIso8601String(),
                ])
                ->values(),
            'syntax' => [
                'collection' => '{{#products}} ... {{/products}}',
                'groups' => $this->tokenReference(),
            ],
        ]);
    }

    private function editor(StoreProductTemplate $template): View
    {
        $previewProducts = $this->catalog
            ->browse(['per_page' => 8, 'page' => 1, 'sort' => 'newest'])['products']
            ->getCollection()
            ->map(fn (array $product): array => $this->browserProduct($product))
            ->values();

        return view('store::admin.templates.editor', [
            'template' => $template,
            'templates' => StoreProductTemplate::query()
                ->latest('updated_at')
                ->paginate(20, ['*'], 'templates_page')
                ->withQueryString(),
            'categories' => [
                'collection' => 'Product Collection',
                'cards' => 'Product Cards',
                'slider' => 'Product Slider',
                'list' => 'Product List',
                'featured' => 'Featured Products',
                'custom' => 'Custom',
            ],
            'tokenGroups' => $this->tokenReference(),
            'previewProducts' => $previewProducts,
            'isSystem' => $template->isSystem(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, ?StoreProductTemplate $template = null): array
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('store_product_templates', 'slug')->ignore($template?->id)],
            'category' => ['required', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'draft'])],
            'html_code' => ['required', 'string', 'max:1000000'],
            'css_code' => ['nullable', 'string', 'max:1000000'],
            'js_code' => ['nullable', 'string', 'max:1000000'],
            'preview_image' => ['nullable', 'string', 'max:2048'],
        ]);

        $unsafeHtml = preg_match('/<\s*(script|iframe|object|embed)\b|\bon[a-z]+\s*=|javascript\s*:/i', (string) $attributes['html_code']) === 1;
        if ($unsafeHtml) {
            throw ValidationException::withMessages([
                'html_code' => 'Executable or embedded content is not allowed in template HTML. Use the JavaScript tab for behavior.',
            ]);
        }
        if (preg_match('/<\s*\/\s*style\b/i', (string) ($attributes['css_code'] ?? '')) === 1) {
            throw ValidationException::withMessages(['css_code' => 'Closing style tags are not allowed in template CSS.']);
        }

        $slug = trim((string) ($attributes['slug'] ?? ''));
        if ($slug === '') {
            $base = Str::slug((string) $attributes['name']) ?: 'product-template';
            $attributes['slug'] = $template?->exists ? $template->slug : $this->uniqueSlug($base);
        }

        return $attributes;
    }

    private function uniqueSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'product-template';
        $slug = $base;
        $suffix = 2;
        while (StoreProductTemplate::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array<string, array<int, array{token:string,description:string}>> */
    private function tokenReference(): array
    {
        return [
            __('store::ui.template_tokens_group_collections') => [
                ['token' => '{{#products}} ... {{/products}}', 'description' => __('store::ui.token_products_repeat')],
                ['token' => '{{#categories}} ... {{/categories}}', 'description' => __('store::ui.token_categories_repeat')],
                ['token' => '{{#tags}} ... {{/tags}}', 'description' => __('store::ui.token_tags_repeat')],
                ['token' => '{{catalog_title}}', 'description' => __('store::ui.token_catalog_title')],
                ['token' => '{{results_count}}', 'description' => __('store::ui.token_results_count')],
                ['token' => '{{site_name}}', 'description' => __('store::ui.token_site_name')],
            ],
            __('store::ui.template_tokens_group_identity') => [
                ['token' => '{{public_id}}', 'description' => __('store::ui.token_public_id')],
                ['token' => '{{name}}', 'description' => __('store::ui.token_name')],
                ['token' => '{{slug}}', 'description' => __('store::ui.token_slug')],
                ['token' => '{{url}}', 'description' => __('store::ui.token_url')],
                ['token' => '{{quick_view_url}}', 'description' => __('store::ui.token_quick_view_url')],
                ['token' => '{{type_code}}', 'description' => __('store::ui.token_type_code')],
                ['token' => '{{sku}}', 'description' => __('store::ui.token_sku')],
                ['token' => '{{variant_id}}', 'description' => __('store::ui.token_variant_id')],
            ],
            __('store::ui.template_tokens_group_content') => [
                ['token' => '{{short_description}}', 'description' => __('store::ui.token_short_description')],
                ['token' => '{{description}}', 'description' => __('store::ui.token_description')],
                ['token' => '{{image}}', 'description' => __('store::ui.token_image')],
                ['token' => '{{image_alt}}', 'description' => __('store::ui.token_image_alt')],
                ['token' => '{{artist_name}}', 'description' => __('store::ui.token_artist_name')],
                ['token' => '{{brand}}', 'description' => __('store::ui.token_brand')],
                ['token' => '{{brand_url}}', 'description' => __('store::ui.token_brand_url')],
                ['token' => '{{categories}}', 'description' => __('store::ui.token_categories')],
                ['token' => '{{tags}}', 'description' => __('store::ui.token_tags')],
            ],
            __('store::ui.template_tokens_group_price') => [
                ['token' => '{{price}}', 'description' => __('store::ui.token_price')],
                ['token' => '{{regular_price}}', 'description' => __('store::ui.token_regular_price')],
                ['token' => '{{currency}}', 'description' => __('store::ui.token_currency')],
                ['token' => '{{availability}}', 'description' => __('store::ui.token_availability')],
                ['token' => '{{availability_label}}', 'description' => __('store::ui.token_availability_label')],
                ['token' => '{{stock_quantity}}', 'description' => __('store::ui.token_stock_quantity')],
                ['token' => '{{badge}}', 'description' => __('store::ui.token_badge')],
            ],
            __('store::ui.template_tokens_group_artwork') => [
                ['token' => '{{completion_year}}', 'description' => __('store::ui.token_completion_year')],
                ['token' => '{{work_type}}', 'description' => __('store::ui.token_work_type')],
                ['token' => '{{edition_type}}', 'description' => __('store::ui.token_edition_type')],
                ['token' => '{{artwork_category}}', 'description' => __('store::ui.token_artwork_category')],
                ['token' => '{{artistic_style}}', 'description' => __('store::ui.token_artistic_style')],
                ['token' => '{{technique}}', 'description' => __('store::ui.token_technique')],
            ],
            __('store::ui.template_tokens_group_conditional') => [
                ['token' => '{{#has_image}} ... {{/has_image}}', 'description' => __('store::ui.token_has_image')],
                ['token' => '{{#on_sale}} ... {{/on_sale}}', 'description' => __('store::ui.token_on_sale')],
                ['token' => '{{#is_featured}} ... {{/is_featured}}', 'description' => __('store::ui.token_is_featured')],
                ['token' => '{{#available}} ... {{/available}}', 'description' => __('store::ui.token_available')],
                ['token' => '{{#is_artwork}} ... {{/is_artwork}}', 'description' => __('store::ui.token_is_artwork')],
            ],
        ];
    }

    /** @param array<string,mixed> $product @return array<string,mixed> */
    private function browserProduct(array $product): array
    {
        $product['categories'] = collect($product['categories'] ?? [])->values()->all();

        return $product;
    }

    private function starterHtml(): string
    {
        return <<<'HTML'
<section class="custom-products">
    <header><span>Selected collection</span><h2>{{catalog_title}}</h2></header>
    <div class="custom-products__grid">
        {{#products}}
        <article class="custom-product">
            <a href="{{url}}">{{#has_image}}<img src="{{image}}" alt="{{image_alt}}">{{/has_image}}<h3>{{name}}</h3></a>
            <p>{{short_description}}</p><strong>{{price}}</strong>
        </article>
        {{/products}}
    </div>
</section>
HTML;
    }

    private function starterCss(): string
    {
        return <<<'CSS'
.custom-products{max-width:1200px;margin:0 auto;padding:32px 20px;font-family:system-ui,sans-serif}
.custom-products header{margin-bottom:20px}.custom-products header span{color:#a90000;font-size:11px;font-weight:800;text-transform:uppercase}
.custom-products header h2{margin:5px 0 0}.custom-products__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.custom-product{overflow:hidden;border:1px solid #e5e7eb;border-radius:12px;background:#fff;padding:14px}.custom-product img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px}.custom-product a{color:inherit;text-decoration:none}.custom-product h3{margin:12px 0 6px}.custom-product p{color:#64748b;font-size:13px}.custom-product strong{color:#991b1b}
@media(max-width:760px){.custom-products__grid{grid-template-columns:1fr}}
CSS;
    }
}
