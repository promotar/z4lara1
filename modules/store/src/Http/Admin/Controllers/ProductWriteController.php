<?php

namespace Modules\Store\Http\Admin\Controllers;

use App\Platform\Core\Localization\PlatformLocale;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Store\Application\Catalog\CreateProduct\CreateProductCommand;
use Modules\Store\Application\Catalog\CreateProduct\CreateProductHandler;
use Modules\Store\Application\Catalog\CreateVariant\CreateVariantCommand;
use Modules\Store\Application\Catalog\CreateVariant\CreateVariantHandler;
use Modules\Store\Application\Catalog\DuplicateProductService;
use Modules\Store\Application\Catalog\ProductAttributeService;
use Modules\Store\Application\Catalog\ProductEditorImage;
use Modules\Store\Application\Catalog\ProductEditorService;
use Modules\Store\Application\Catalog\ProductEditorUpdate;
use Modules\Store\Application\Catalog\ProductLifecycleService;
use Modules\Store\Application\Catalog\ProductOrganizationService;
use Modules\Store\Application\Catalog\ProductPolicyService;
use Modules\Store\Application\Catalog\ProductPublicationService;
use Modules\Store\Application\Catalog\ProductScopeService;
use Modules\Store\Application\Catalog\ProductVisibilityService;
use Modules\Store\Application\Catalog\UpdateProduct\UpdateProductCommand;
use Modules\Store\Application\Catalog\UpdateProduct\UpdateProductHandler;
use Modules\Store\Application\Catalog\VariantEditorService;
use Modules\Store\Application\Catalog\VariantEditorUpdate;
use Modules\Store\Application\Catalog\VariantLifecycleService;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Application\Media\ProductMediaService;
use Modules\Store\Application\Media\UploadProductImageService;
use Modules\Store\Application\Pricing\ScheduleSalePrice;
use Modules\Store\Application\Pricing\SetRegularPrice;
use Modules\Store\Domain\Catalog\ProductVisibility;
use Modules\Store\Domain\Catalog\VariantStatus;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Media\MediaSource;
use Modules\Store\Domain\Media\MediaUploadCandidate;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Domain\Shared\TransactionManager;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class ProductWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function __construct(ProductScopeService $products)
    {
        $this->middleware(function (Request $request, callable $next) use ($products) {
            $productPublicId = $request->route('product');
            if (is_string($productPublicId) && $productPublicId !== '') {
                abort_if($products->isArtwork($productPublicId), 409, 'Artwork products must be changed through the dedicated Store artwork workflow.');
            }

            return $next($request);
        });
    }

    public function store(
        Request $request,
        CreateProductHandler $handler,
        CreateVariantHandler $variantCreator,
        UpdateProductHandler $updater,
        ProductOrganizationService $organization,
        ProductAttributeService $productAttributes,
        SetRegularPrice $regularPrices,
        ScheduleSalePrice $salePrices,
        InventoryService $inventory,
        UploadProductImageService $media,
        ProductMediaService $gallery,
        ProductPolicyService $policies,
        ProductPublicationService $publisher,
        TransactionManager $transactions,
    ): RedirectResponse {
        if ($request->routeIs('admin.plugins.store.products.store-publish')) {
            $request->merge(['intent' => 'publish']);
        }

        $data = $request->validate([
            'type_code' => ['required', 'in:simple,variant'],
            'intent' => ['nullable', 'in:draft,publish'],
            'locale' => ['required', 'regex:/^[a-z]{2}(?:[-_][A-Z]{2})?$/'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('store_product_translations', 'slug')
                    ->where(fn ($query) => $query->where('locale', (string) $request->input('locale'))),
            ],
            'visibility' => ['required', 'in:public,hidden'],
            'internal_code' => ['nullable', 'string', 'max:128', Rule::unique('store_products', 'internal_code')],
            'artist_name' => ['nullable', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'required_if:type_code,simple',
                'string',
                'max:64',
                Rule::unique('store_product_variants', 'sku'),
            ],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:50000'],
            'featured' => ['nullable', 'boolean'],
            'brand' => ['nullable', 'string', 'size:26'],
            'tags' => ['nullable', 'string', 'max:2500'],
            'shipping_policy_mode' => ['nullable', 'in:inherit,custom'],
            'shipping_policy' => ['nullable', 'required_if:shipping_policy_mode,custom', 'string', 'max:20000'],
            'return_policy_mode' => ['nullable', 'in:inherit,custom'],
            'return_policy' => ['nullable', 'required_if:return_policy_mode,custom', 'string', 'max:20000'],
            'categories' => ['nullable', 'required_if:intent,publish', 'array', 'min:1'],
            'categories.*' => ['string', 'size:26'],
            'primary_category' => ['nullable', 'string', 'size:26'],
            'option_axes' => ['exclude_unless:type_code,variant', 'required_if:type_code,variant', 'array', 'min:1', 'max:4'],
            'option_axes.*.name' => ['required', 'string', 'max:80'],
            'option_axes.*.values' => ['required', 'array', 'min:1', 'max:20'],
            'option_axes.*.values.*' => ['required', 'string', 'max:100'],
            'variants' => ['nullable', 'required_if:type_code,variant', 'array', 'min:1', 'max:100'],
            'variants.*.options' => ['required', 'array', 'min:1', 'max:4'],
            'variants.*.options.*' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct', Rule::unique('store_product_variants', 'sku')],
            'variants.*.regular_price' => ['nullable', 'required_if:intent,publish', 'required_with:variants.*.sale_price', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'variants.*.sale_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'variants.*.sale_starts_at' => ['nullable', 'date'],
            'variants.*.sale_ends_at' => ['nullable', 'date', 'after:variants.*.sale_starts_at'],
            'variants.*.stock_status' => ['nullable', 'required_if:intent,publish', 'in:in_stock,out_of_stock'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.allow_backorder' => ['nullable', 'boolean'],
            'variants.*.image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'variants.*.asset' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('store_media_assets', 'public_id')->where(fn ($query) => $query->where('status', 'ready')),
            ],
            'regular_price' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('intent') === 'publish' && $request->input('type_code') === 'simple'),
                'required_with:sale_price',
                'regex:/^\d+(?:\.\d{1,3})?$/',
            ],
            'sale_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date'],
            'stock_status' => ['nullable', 'in:in_stock,out_of_stock'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'allow_backorder' => ['nullable', 'boolean'],
            'main_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'main_asset' => ['nullable', 'string', 'size:26'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'gallery_assets' => ['nullable', 'array', 'max:8'],
            'gallery_assets.*' => ['string', 'size:26', 'distinct'],
        ], [
            'slug.unique' => 'This product URL is already used. Choose a different slug.',
            'internal_code.unique' => 'This internal code is already used by another product.',
            'sku.unique' => 'This SKU is already used by another product or variant.',
            'variants.*.sku.unique' => 'This SKU is already used by another product or variant.',
            'variants.*.sku.distinct' => 'Each variant must have a different SKU.',
            'option_axes.*.name.required' => 'Enter a name for this attribute.',
            'option_axes.*.values.required' => 'Add at least one value to this attribute.',
            'variants.*.regular_price.required_if' => 'Enter a regular price for this variant before publishing.',
            'variants.*.stock_status.required_if' => 'Choose the availability of this variant before publishing.',
            'shipping_policy.required_if' => 'Enter the custom shipping policy or use the general Store policy.',
            'return_policy.required_if' => 'Enter the custom return policy or use the general Store policy.',
        ]);
        $switchLocale = (string) $request->input('switch_locale', '');
        $switchSuffix = '';
        if ($switchLocale !== '' && array_key_exists($switchLocale, app(PlatformLocale::class)->languages())) {
            $switchSuffix = '?locale='.urlencode($switchLocale);
        }

        if (($data['intent'] ?? 'draft') === 'publish' && ! $request->hasFile('main_image') && empty($data['main_asset'])) {
            return back()->withInput()->withErrors(['main_image' => 'Choose a main image from the gallery or upload a file before publishing.']);
        }
        if (($data['intent'] ?? 'draft') === 'publish') {
            $this->ensureProductPermission($request, 'store.products.publish');
        }
        $variantOptionReferences = [];
        if ($data['type_code'] === 'variant') {
            $seenAxisNames = [];
            foreach ($data['option_axes'] as $axisIndex => $axisData) {
                $axisName = $this->normalizeOptionLabel($axisData['name']);
                $axisNameKey = mb_strtolower($axisName);
                if (isset($seenAxisNames[$axisNameKey])) {
                    return back()->withInput()->withErrors([
                        "option_axes.{$axisIndex}.name" => 'Each attribute must have a unique name.',
                    ]);
                }
                $seenAxisNames[$axisNameKey] = true;
                $seenValues = [];
                foreach ($axisData['values'] as $valueIndex => $value) {
                    $normalized = $this->normalizeOptionLabel($value);
                    $valueKey = mb_strtolower($normalized);
                    if (isset($seenValues[$valueKey])) {
                        return back()->withInput()->withErrors([
                            "option_axes.{$axisIndex}.values.{$valueIndex}" => 'Each value within an attribute must be unique.',
                        ]);
                    }
                    $seenValues[$valueKey] = true;
                    $data['option_axes'][$axisIndex]['values'][$valueIndex] = $normalized;
                }
                $data['option_axes'][$axisIndex]['name'] = $axisName;
            }

            $seenVariantCombinations = [];
            foreach ($data['variants'] as $position => $variantData) {
                $referencesByAxis = [];
                foreach ($variantData['options'] as $reference) {
                    [$axisIndex, $valueIndex] = array_map('intval', explode(':', $reference, 2));
                    if (! isset($data['option_axes'][$axisIndex]['values'][$valueIndex]) || isset($referencesByAxis[$axisIndex])) {
                        return back()->withInput()->withErrors([
                            "variants.{$position}.options" => 'Each variant must select one valid value from every attribute.',
                        ]);
                    }
                    $referencesByAxis[$axisIndex] = $valueIndex;
                }
                ksort($referencesByAxis);
                if (count($referencesByAxis) !== count($data['option_axes'])
                    || array_keys($referencesByAxis) !== array_keys($data['option_axes'])) {
                    return back()->withInput()->withErrors([
                        "variants.{$position}.options" => 'Each variant must select exactly one value from every attribute.',
                    ]);
                }
                $variantOptionReferences[$position] = $referencesByAxis;
                $combinationKey = implode('|', array_map(
                    fn (int $axisIndex, int $valueIndex): string => "{$axisIndex}:{$valueIndex}",
                    array_keys($referencesByAxis),
                    array_values($referencesByAxis),
                ));
                if (isset($seenVariantCombinations[$combinationKey])) {
                    return back()->withInput()->withErrors([
                        "variants.{$position}.options" => 'Each sellable variant must use a unique option combination.',
                    ]);
                }
                $seenVariantCombinations[$combinationKey] = true;
            }
        }
        try {
            return $transactions->run(function () use (
                $data,
                $request,
                $handler,
                $variantCreator,
                $updater,
                $organization,
                $productAttributes,
                $regularPrices,
                $salePrices,
                $inventory,
                $media,
                $gallery,
                $policies,
                $publisher,
                $variantOptionReferences,
                $switchSuffix,
            ): RedirectResponse {
                $intent = $data['intent'] ?? 'draft';
                $actor = $this->actor($request);
                $resolvedVariantAxes = [];
                $resolvedVariantOptions = [];
                $variantOptionCodeMaps = [];
                if ($data['type_code'] === 'variant') {
                    foreach ($data['option_axes'] as $axisIndex => $axisData) {
                        $attribute = $productAttributes->resolveVariantAttribute($data['locale'], $axisData['name']);
                        $resolvedVariantAxes[$axisIndex] = $attribute;
                        foreach ($axisData['values'] as $valueIndex => $value) {
                            $resolvedVariantOptions[$axisIndex][$valueIndex] = $productAttributes->resolveVariantOption(
                                $data['locale'],
                                $attribute,
                                $value,
                                $valueIndex,
                            );
                        }
                    }
                    foreach ($variantOptionReferences as $position => $referencesByAxis) {
                        foreach ($referencesByAxis as $axisIndex => $valueIndex) {
                            $variantOptionCodeMaps[$position][$resolvedVariantAxes[$axisIndex]['code']]
                                = $resolvedVariantOptions[$axisIndex][$valueIndex]['code'];
                        }
                        ksort($variantOptionCodeMaps[$position]);
                    }
                }
                $result = $handler->handle(new CreateProductCommand(
                    $data['type_code'], $data['locale'], $data['name'], $data['slug'],
                    ProductVisibility::from($data['visibility']), $actor,
                    $data['internal_code'] ?? null, $data['sku'] ?? null,
                ));

                $hasDetails = ($data['short_description'] ?? null) !== null
                    || ($data['description'] ?? null) !== null
                    || ($data['artist_name'] ?? null) !== null
                    || ($data['brand'] ?? null) !== null
                    || ($data['tags'] ?? null) !== null
                    || $request->boolean('featured');
                if ($hasDetails) {
                    $tags = preg_split('/[,;\r\n]+/u', (string) ($data['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $updater->handle(new UpdateProductCommand(
                        $result->productPublicId,
                        $result->version,
                        $data['locale'],
                        $data['name'],
                        $data['slug'],
                        ProductVisibility::from($data['visibility']),
                        $actor,
                        $data['internal_code'] ?? null,
                        $data['artist_name'] ?? null,
                        $data['short_description'] ?? null,
                        $data['description'] ?? null,
                        $request->boolean('featured'),
                        $data['brand'] ?? null,
                        $tags,
                    ));
                }
                if (array_key_exists('categories', $data)) {
                    $organization->syncCategories($result->productPublicId, $data['categories'], $data['primary_category'] ?? null);
                }
                $policies->save(
                    $result->productPublicId,
                    $data['shipping_policy_mode'] ?? 'inherit',
                    $data['shipping_policy'] ?? null,
                    $data['return_policy_mode'] ?? 'inherit',
                    $data['return_policy'] ?? null,
                    $actor,
                );
                if ($data['type_code'] === 'variant') {
                    foreach ($resolvedVariantAxes as $position => $attribute) {
                        $productAttributes->assignToProduct(
                            $result->productPublicId,
                            $attribute['public_id'],
                            required: false,
                            variantAxis: true,
                            filterable: false,
                            visible: true,
                            position: $position,
                        );
                    }
                }
                $createdVariantPublicIds = [];
                if ($data['type_code'] === 'variant') {
                    foreach ($data['variants'] as $position => $variantData) {
                        $createdVariant = $variantCreator->handle(new CreateVariantCommand(
                            $result->productPublicId,
                            $variantData['sku'],
                            $variantOptionCodeMaps[$position],
                            $actor,
                            VariantStatus::Inactive,
                            $position === 0,
                        ));
                        $variantPublicId = $createdVariant->variantPublicId;
                        $createdVariantPublicIds[] = $variantPublicId;

                        if (($variantData['regular_price'] ?? null) !== null) {
                            $regularPrices->execute($variantPublicId, Money::fromDecimal($variantData['regular_price'], 'JOD'), $actor);
                        }
                        if (($variantData['sale_price'] ?? null) !== null) {
                            $startsAt = new DateTimeImmutable($variantData['sale_starts_at'] ?? now()->format(DATE_ATOM));
                            $endsAt = isset($variantData['sale_ends_at']) ? new DateTimeImmutable($variantData['sale_ends_at']) : null;
                            $salePrices->execute($variantPublicId, Money::fromDecimal($variantData['sale_price'], 'JOD'), $startsAt, $endsAt, $actor);
                        }
                        if (array_key_exists('stock_status', $variantData)
                            || array_key_exists('stock_quantity', $variantData)
                            || array_key_exists('allow_backorder', $variantData)) {
                            $quantityProvided = array_key_exists('stock_quantity', $variantData) && $variantData['stock_quantity'] !== null;
                            $stockStatus = $variantData['stock_status'] ?? 'in_stock';
                            $tracking = $quantityProvided || $stockStatus === 'out_of_stock'
                                ? InventoryTrackingMode::Tracked
                                : InventoryTrackingMode::Untracked;
                            $inventory->createItem(
                                $variantPublicId,
                                $tracking,
                                $quantityProvided ? (int) $variantData['stock_quantity'] : ($stockStatus === 'in_stock' ? 1 : 0),
                                (bool) ($variantData['allow_backorder'] ?? false),
                                0,
                                $actor,
                                (string) Str::uuid(),
                            );
                        }
                        if (($variantImage = $request->file("variants.{$position}.image")) instanceof UploadedFile) {
                            $this->uploadCreatedImage(
                                $media,
                                $result->productPublicId,
                                $variantImage,
                                MediaRole::Cover,
                                true,
                                0,
                                $data['locale'],
                                $actor,
                                $variantPublicId,
                            );
                        } elseif (! empty($variantData['asset'])) {
                            $gallery->attach(
                                $result->productPublicId,
                                $variantPublicId,
                                new MediaSource(assetPublicId: $variantData['asset']),
                                MediaRole::Cover,
                                true,
                                0,
                                $data['locale'],
                            );
                        }
                    }
                }

                if ($result->defaultVariantPublicId !== null) {
                    if (($data['regular_price'] ?? null) !== null) {
                        $regularPrices->execute($result->defaultVariantPublicId, Money::fromDecimal($data['regular_price'], 'JOD'), $actor);
                    }
                    if (($data['sale_price'] ?? null) !== null) {
                        $startsAt = new DateTimeImmutable($data['sale_starts_at'] ?? now()->format(DATE_ATOM));
                        $endsAt = isset($data['sale_ends_at']) ? new DateTimeImmutable($data['sale_ends_at']) : null;
                        $salePrices->execute($result->defaultVariantPublicId, Money::fromDecimal($data['sale_price'], 'JOD'), $startsAt, $endsAt, $actor);
                    }

                    if ($request->has('stock_status') || $request->has('stock_quantity') || $request->has('allow_backorder')) {
                        $quantityProvided = array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null;
                        $stockStatus = $data['stock_status'] ?? 'in_stock';
                        $tracking = $quantityProvided || $stockStatus === 'out_of_stock'
                            ? InventoryTrackingMode::Tracked
                            : InventoryTrackingMode::Untracked;
                        $inventory->createItem(
                            $result->defaultVariantPublicId,
                            $tracking,
                            $quantityProvided ? (int) $data['stock_quantity'] : ($stockStatus === 'in_stock' ? 1 : 0),
                            $request->boolean('allow_backorder'),
                            0,
                            $actor,
                            (string) Str::uuid(),
                        );
                    }
                }

                if (($image = $request->file('main_image')) instanceof UploadedFile) {
                    $this->uploadCreatedImage($media, $result->productPublicId, $image, MediaRole::Cover, true, 0, $data['locale'], $actor);
                }
                foreach ($request->file('gallery', []) as $position => $image) {
                    if ($image instanceof UploadedFile) {
                        $this->uploadCreatedImage($media, $result->productPublicId, $image, MediaRole::Gallery, false, $position + 1, $data['locale'], $actor);
                    }
                }
                if (! empty($data['main_asset'])) {
                    $gallery->attach($result->productPublicId, null, new MediaSource(assetPublicId: $data['main_asset']), MediaRole::Cover, true, 0, $data['locale']);
                }
                $galleryOffset = count($request->file('gallery', []));
                foreach ($data['gallery_assets'] ?? [] as $position => $assetPublicId) {
                    $gallery->attach($result->productPublicId, null, new MediaSource(assetPublicId: $assetPublicId), MediaRole::Gallery, false, $galleryOffset + $position + 1, $data['locale']);
                }


                $publishVariantPublicIds = $result->defaultVariantPublicId !== null
                    ? [$result->defaultVariantPublicId]
                    : $createdVariantPublicIds;
                if ($intent === 'publish' && $publishVariantPublicIds !== []) {
                    $publisher->publish(
                        $result->productPublicId,
                        $hasDetails ? $result->version + 1 : $result->version,
                        $actor,
                    );

                    return redirect()->to(route('admin.plugins.store.products.edit', $result->productPublicId).$switchSuffix)
                        ->with('success', 'Product created and published.');
                }

                if ($data['type_code'] === 'variant') {
                    return redirect()->to(route('admin.plugins.store.products.edit', $result->productPublicId).$switchSuffix.'#product-options')
                        ->with('success', 'Variable product draft created with its option axes and sellable variants.');
                }

                return redirect()->to(route('admin.plugins.store.products.edit', $result->productPublicId).$switchSuffix)
                    ->with('success', 'Product draft created with its catalog information, pricing, inventory, and media. Review it before publishing.');
            });
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function update(Request $request, string $product, ProductEditorService $service): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'], 'locale' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('store_product_translations', 'slug')->where(
                    fn ($query) => $query
                        ->where('locale', (string) $request->input('locale'))
                        ->whereNotIn('product_id', fn ($products) => $products
                            ->select('id')
                            ->from('store_products')
                            ->where('public_id', $product)),
                ),
            ],
            'visibility' => ['required', 'in:public,hidden'],
            'internal_code' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('store_products', 'internal_code')->ignore($product, 'public_id'),
            ],
            'artist_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:2000'], 'description' => ['nullable', 'string', 'max:50000'],
            'featured' => ['nullable', 'boolean'],
            'brand' => ['nullable', 'string', 'size:26'],
            'tags' => ['nullable', 'string', 'max:2500'],
            'shipping_policy_mode' => ['nullable', 'in:inherit,custom'],
            'shipping_policy' => ['nullable', 'required_if:shipping_policy_mode,custom', 'string', 'max:20000'],
            'return_policy_mode' => ['nullable', 'in:inherit,custom'],
            'return_policy' => ['nullable', 'required_if:return_policy_mode,custom', 'string', 'max:20000'],
            'published_at' => ['nullable', 'date', 'before_or_equal:now'],
            'intent' => ['nullable', 'in:draft,publish'],
            'categories_present' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'], 'categories.*' => ['string', 'size:26'],
            'primary_category' => ['nullable', 'string', 'size:26'],
            'regular_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'regular_price_initial' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_starts_at' => ['nullable', 'date'], 'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'active_sale' => ['nullable', 'string', 'size:26'],
            'sale_price_initial' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_starts_at_initial' => ['nullable', 'date'],
            'sale_ends_at_initial' => ['nullable', 'date'],
            'inventory_present' => ['nullable', 'boolean'],
            'stock_status' => ['nullable', 'in:in_stock,out_of_stock'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'allow_backorder' => ['nullable', 'boolean'],
            'main_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'main_asset' => ['nullable', 'string', 'size:26'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'gallery_assets' => ['nullable', 'array', 'max:8'],
            'gallery_assets.*' => ['string', 'size:26', 'distinct'],
        ], [
            'slug.unique' => 'This product URL is already used. Choose a different slug.',
            'internal_code.unique' => 'This internal code is already used by another product.',
            'shipping_policy.required_if' => 'Enter the custom shipping policy or use the general Store policy.',
            'return_policy.required_if' => 'Enter the custom return policy or use the general Store policy.',
        ]);
        if ($request->hasFile('main_image') && ! empty($data['main_asset'])) {
            return back()->withInput()->withErrors(['main_image' => 'Choose either a gallery image or an uploaded file as the new main image.']);
        }
        if (($data['intent'] ?? 'draft') === 'publish') {
            $this->ensureProductPermission($request, 'store.products.publish');
        }
        try {
            $tags = preg_split('/[,;\r\n]+/u', (string) ($data['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $gallery = [];
            foreach ($request->file('gallery', []) as $image) {
                if ($image instanceof UploadedFile) {
                    $gallery[] = $this->editorImage($image);
                }
            }
            $mainImage = ($image = $request->file('main_image')) instanceof UploadedFile ? $this->editorImage($image) : null;
            $salePrice = $data['sale_price'] ?? null;
            $saleChanged = $salePrice !== null && (
                $salePrice !== ($data['sale_price_initial'] ?? null)
                || ($data['sale_starts_at'] ?? null) !== ($data['sale_starts_at_initial'] ?? null)
                || ($data['sale_ends_at'] ?? null) !== ($data['sale_ends_at_initial'] ?? null)
            );
            $service->save(new ProductEditorUpdate(
                $product, (int) $data['version'], $data['locale'], $data['name'], $data['slug'],
                ProductVisibility::from($data['visibility']), $this->actor($request), $data['internal_code'] ?? null,
                $data['artist_name'] ?? null,
                $data['short_description'] ?? null, $data['description'] ?? null, $request->boolean('featured'),
                $data['brand'] ?? null, $tags,
                ! empty($data['published_at']) ? new DateTimeImmutable($data['published_at']) : null,
                $request->boolean('categories_present'), $data['categories'] ?? [], $data['primary_category'] ?? null,
                (($data['regular_price'] ?? null) === ($data['regular_price_initial'] ?? null)) ? null : ($data['regular_price'] ?? null), $saleChanged ? $salePrice : null,
                $saleChanged && ! empty($data['sale_starts_at']) ? new DateTimeImmutable($data['sale_starts_at']) : null,
                $saleChanged && ! empty($data['sale_ends_at']) ? new DateTimeImmutable($data['sale_ends_at']) : null,
                $saleChanged ? ($data['active_sale'] ?? null) : null,
                $request->boolean('inventory_present'), $data['stock_status'] ?? 'in_stock',
                array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null ? (int) $data['stock_quantity'] : null,
                $request->boolean('allow_backorder'), $mainImage, $data['main_asset'] ?? null,
                $gallery, $data['gallery_assets'] ?? [],
                $data['shipping_policy_mode'] ?? null, $data['shipping_policy'] ?? null,
                $data['return_policy_mode'] ?? null, $data['return_policy'] ?? null,
                ($data['intent'] ?? 'draft') === 'publish',
            ));


            return back()->with('success', ($data['intent'] ?? 'draft') === 'publish'
                ? 'Product saved and published.'
                : 'Product changes saved without changing its publication state.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function duplicate(Request $request, string $product, DuplicateProductService $service): RedirectResponse
    {
        try {
            $duplicate = $service->duplicate($product, $this->actor($request));

            return redirect()->route('admin.plugins.store.products.edit', $duplicate)
                ->with('success', 'Product duplicated as a hidden draft. Add unique SKUs, pricing, and inventory before publishing.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function visibility(
        Request $request,
        string $product,
        ProductVisibilityService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'visibility' => ['required', 'in:public,hidden'],
        ]);
        try {
            $service->set(
                $product,
                (int) $data['version'],
                ProductVisibility::from($data['visibility']),
                $this->actor($request),
            );

            return back()->with('success', $data['visibility'] === 'hidden'
                ? 'Product hidden from the catalog.'
                : 'Product is visible in the catalog when published.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function lifecycle(
        Request $request,
        string $product,
        ProductLifecycleService $service,
        ProductPublicationService $publisher,
    ): RedirectResponse {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', 'in:publish,archive,restore']]);
        $this->ensureProductPermission($request, $data['action'] === 'publish' ? 'store.products.publish' : 'store.products.archive');
        try {
            if ($data['action'] === 'publish') {
                $publisher->publish($product, (int) $data['version'], $this->actor($request));
            } else {
                $service->{$data['action']}($product, (int) $data['version'], $this->actor($request));
            }

            return back()->with('success', 'Product lifecycle updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function publish(Request $request, string $product, ProductPublicationService $service): RedirectResponse
    {
        return $this->runLifecycle($request, $product, $service, 'publish', 'Product published.');
    }

    public function archive(Request $request, string $product, ProductLifecycleService $service): RedirectResponse
    {
        return $this->runLifecycle($request, $product, $service, 'archive', 'Product deleted safely. Its data was preserved and can be restored.');
    }

    public function restore(Request $request, string $product, ProductLifecycleService $service): RedirectResponse
    {
        return $this->runLifecycle($request, $product, $service, 'restore', 'Product restored.');
    }

    public function storeVariant(Request $request, string $product, CreateVariantHandler $handler): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('store_product_variants', 'sku')],
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'max:128'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'sku.unique' => 'This SKU is already used by another product or variant.',
        ]);
        try {
            $handler->handle(new CreateVariantCommand($product, $data['sku'], $data['options'], $this->actor($request), VariantStatus::Inactive, $request->boolean('is_default')));

            return back()->with('success', 'Variant created in inactive state.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function updateVariant(
        Request $request,
        string $product,
        string $variant,
        VariantEditorService $service,
        ProductScopeService $scope,
    ): RedirectResponse {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'sku' => [
                'required',
                'string',
                'max:64',
                Rule::unique('store_product_variants', 'sku')->ignore($variant, 'public_id'),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('store_product_variants', 'barcode')->ignore($variant, 'public_id'),
            ],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'regular_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'regular_price_initial' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'active_sale' => ['nullable', 'string', 'size:26'],
            'sale_price_initial' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'sale_starts_at_initial' => ['nullable', 'date'],
            'sale_ends_at_initial' => ['nullable', 'date'],
            'inventory_present' => ['nullable', 'boolean'],
            'stock_status' => ['nullable', 'required_if:inventory_present,1', 'in:in_stock,out_of_stock'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'allow_backorder' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'locale' => ['nullable', 'string', 'max:16'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ], [
            'sku.unique' => 'This SKU is already used by another product or variant.',
            'barcode.unique' => 'This barcode is already used by another variant.',
        ]);
        try {
            $scope->assertVariantBelongsToProduct($product, $variant);
            $image = ($uploadedImage = $request->file('image')) instanceof UploadedFile
                ? $this->editorImage($uploadedImage)
                : null;
            $regularPrice = $data['regular_price'] ?? null;
            $salePrice = $data['sale_price'] ?? null;
            $saleChanged = $salePrice !== null && (
                $salePrice !== ($data['sale_price_initial'] ?? null)
                || ($data['sale_starts_at'] ?? null) !== ($data['sale_starts_at_initial'] ?? null)
                || ($data['sale_ends_at'] ?? null) !== ($data['sale_ends_at_initial'] ?? null)
            );
            $service->save(new VariantEditorUpdate(
                productPublicId: $product,
                variantPublicId: $variant,
                expectedVersion: (int) $data['version'],
                sku: $data['sku'],
                barcode: $data['barcode'] ?? null,
                position: (int) $data['position'],
                actor: $this->actor($request),
                regularPrice: $regularPrice === ($data['regular_price_initial'] ?? null) ? null : $regularPrice,
                salePrice: $saleChanged ? $salePrice : null,
                saleStartsAt: $saleChanged && ! empty($data['sale_starts_at']) ? new DateTimeImmutable($data['sale_starts_at']) : null,
                saleEndsAt: $saleChanged && ! empty($data['sale_ends_at']) ? new DateTimeImmutable($data['sale_ends_at']) : null,
                replaceSalePublicId: $saleChanged ? ($data['active_sale'] ?? null) : null,
                updateInventory: $request->boolean('inventory_present'),
                stockStatus: $data['stock_status'] ?? 'in_stock',
                stockQuantity: array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null
                    ? (int) $data['stock_quantity']
                    : null,
                allowBackorder: $request->boolean('allow_backorder'),
                image: $image,
                locale: $data['locale'] ?? null,
                idempotencyKey: $data['idempotency_key'] ?? 'variant-editor-'.$variant.'-'.(int) $data['version'],
            ));

            return back()->with('success', 'Variant saved. It will be activated automatically when the product is published.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function variantLifecycle(Request $request, string $product, string $variant, VariantLifecycleService $service, ProductScopeService $scope): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', 'in:activate,deactivate,archive']]);
        try {
            $scope->assertVariantBelongsToProduct($product, $variant);
            $service->{$data['action']}($variant, (int) $data['version'], $this->actor($request));

            return back()->with('success', 'Variant lifecycle updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function regularPrice(Request $request, string $product, string $variant, SetRegularPrice $service, ProductScopeService $scope): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'priority' => ['nullable', 'integer', 'between:-100000,100000']]);
        try {
            $scope->assertVariantBelongsToProduct($product, $variant);
            $service->execute($variant, Money::fromDecimal($data['amount'], 'JOD'), $this->actor($request), (int) ($data['priority'] ?? 0));

            return back()->with('success', 'Regular price saved with history preserved.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function salePrice(Request $request, string $product, string $variant, ScheduleSalePrice $service, ProductScopeService $scope): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'priority' => ['nullable', 'integer', 'between:-100000,100000']]);
        try {
            $scope->assertVariantBelongsToProduct($product, $variant);
            $service->execute($variant, Money::fromDecimal($data['amount'], 'JOD'), new DateTimeImmutable($data['starts_at']), new DateTimeImmutable($data['ends_at']), $this->actor($request), (int) ($data['priority'] ?? 0));

            return back()->with('success', 'Sale price scheduled.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function createInventory(Request $request, string $product, string $variant, InventoryService $service, ProductScopeService $scope): RedirectResponse
    {
        $data = $request->validate(['tracking_mode' => ['required', 'in:tracked,untracked'], 'opening_on_hand' => ['required', 'integer'], 'safety_stock' => ['required', 'integer', 'min:0'], 'allow_backorder' => ['nullable', 'boolean'], 'idempotency_key' => ['required', 'string', 'max:191']]);
        try {
            $scope->assertVariantBelongsToProduct($product, $variant);
            $service->createItem($variant, InventoryTrackingMode::from($data['tracking_mode']), (int) $data['opening_on_hand'], $request->boolean('allow_backorder'), (int) $data['safety_stock'], $this->actor($request), $data['idempotency_key']);

            return back()->with('success', 'Inventory item initialized with an opening ledger movement.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function organization(Request $request, string $product, ProductOrganizationService $service): RedirectResponse
    {
        $data = $request->validate(['categories' => ['nullable', 'array'], 'categories.*' => ['string', 'size:26'], 'primary_category' => ['nullable', 'string', 'size:26']]);
        try {
            $service->syncCategories($product, $data['categories'] ?? [], $data['primary_category'] ?? null);

            return back()->with('success', 'Product categories updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function assignAttribute(Request $request, string $product, ProductAttributeService $service): RedirectResponse
    {
        $data = $request->validate(['attribute' => ['required', 'string', 'size:26'], 'required' => ['nullable', 'boolean'], 'variant_axis' => ['nullable', 'boolean'], 'filterable' => ['nullable', 'boolean'], 'visible' => ['nullable', 'boolean'], 'position' => ['nullable', 'integer', 'min:0']]);
        try {
            $service->assignToProduct($product, $data['attribute'], $request->boolean('required'), $request->boolean('variant_axis'), $request->boolean('filterable'), $request->boolean('visible'), (int) ($data['position'] ?? 0));

            return back()->with('success', 'Attribute assigned to product.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function setAttributeValue(Request $request, string $product, string $attribute, ProductAttributeService $service): RedirectResponse
    {
        $data = $request->validate(['value' => ['nullable']]);
        try {
            $service->setProductValue($product, $attribute, $data['value'] ?? null);

            return back()->with('success', 'Product attribute value updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function uploadMedia(Request $request, string $product, UploadProductImageService $service): RedirectResponse
    {
        $data = $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'], 'variant' => ['nullable', 'string', 'size:26'], 'role' => ['required', 'in:cover,gallery,thumbnail,swatch'], 'primary' => ['nullable', 'boolean'], 'position' => ['nullable', 'integer', 'min:0'], 'locale' => ['nullable', 'string', 'max:16'], 'alt_text' => ['nullable', 'string', 'max:1000']]);
        $file = $data['image'];
        $dimensions = @getimagesize($file->getPathname());
        try {
            $service->upload($product, $data['variant'] ?? null, $file->getPathname(), new MediaUploadCandidate($file->getClientOriginalName(), (string) $file->getMimeType(), (int) $file->getSize(), $dimensions === false ? null : $dimensions[0], $dimensions === false ? null : $dimensions[1]), MediaRole::from($data['role']), $request->boolean('primary'), (int) ($data['position'] ?? 0), $this->actor($request), $data['locale'] ?? null, $data['alt_text'] ?? null);

            return back()->with('success', 'Product image uploaded and associated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function removeMedia(Request $request, string $product, string $media, ProductMediaService $service): RedirectResponse
    {
        try {
            $service->remove($product, $media);

            return back()->with('success', 'Media association removed; shared binary data was preserved safely.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function reorderMedia(Request $request, string $product, ProductMediaService $service): RedirectResponse
    {
        $data = $request->validate([
            'media' => ['required', 'array', 'min:1', 'max:100'],
            'media.*' => ['required', 'string', 'size:26', 'distinct'],
        ]);
        try {
            $service->reorder($product, $data['media']);

            return back()->with('success', 'Product gallery order updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function normalizeOptionLabel(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function runLifecycle(
        Request $request,
        string $product,
        ProductLifecycleService|ProductPublicationService $service,
        string $action,
        string $message,
    ): RedirectResponse {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $this->ensureProductPermission($request, $action === 'publish' ? 'store.products.publish' : 'store.products.archive');
        try {
            $service->{$action}($product, (int) $data['version'], $this->actor($request));

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function uploadCreatedImage(
        UploadProductImageService $service,
        string $productPublicId,
        UploadedFile $file,
        MediaRole $role,
        bool $primary,
        int $position,
        string $locale,
        ActorContext $actor,
        ?string $variantPublicId = null,
    ): void {
        $dimensions = @getimagesize($file->getPathname());
        $service->upload(
            $productPublicId,
            $variantPublicId,
            $file->getPathname(),
            new MediaUploadCandidate(
                $file->getClientOriginalName(),
                (string) $file->getMimeType(),
                (int) $file->getSize(),
                $dimensions === false ? null : $dimensions[0],
                $dimensions === false ? null : $dimensions[1],
            ),
            $role,
            $primary,
            $position,
            $actor,
            $locale,
        );
    }

    private function editorImage(UploadedFile $file): ProductEditorImage
    {
        $dimensions = @getimagesize($file->getPathname());

        return new ProductEditorImage(
            $file->getPathname(),
            new MediaUploadCandidate(
                $file->getClientOriginalName(),
                (string) $file->getMimeType(),
                (int) $file->getSize(),
                $dimensions === false ? null : $dimensions[0],
                $dimensions === false ? null : $dimensions[1],
            ),
        );
    }

    private function ensureProductPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->hasRole('super-admin') || $user->can($permission)), 403);
    }
}
