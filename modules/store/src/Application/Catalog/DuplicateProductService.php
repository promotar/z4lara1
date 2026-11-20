<?php

namespace Modules\Store\Application\Catalog;

use DomainException;
use Illuminate\Database\ConnectionInterface;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\PublicIdGenerator;

final readonly class DuplicateProductService
{
    public function __construct(
        private ConnectionInterface $database,
        private PublicIdGenerator $ids,
        private Clock $clock,
    ) {}

    public function duplicate(string $productPublicId, ActorContext $actor): string
    {
        return $this->database->transaction(function () use ($productPublicId, $actor): string {
            $source = $this->database->table('store_products')
                ->where('public_id', $productPublicId)
                ->lockForUpdate()
                ->first();
            if ($source === null) {
                throw new ProductNotFound('Product was not found.');
            }
            if ($source->type_code === 'artwork') {
                throw new DomainException('Artwork products must be duplicated through the Store artwork workflow.');
            }

            $now = $this->clock->now();
            $publicId = (string) $this->ids->generate();
            $productId = (int) $this->database->table('store_products')->insertGetId([
                'public_id' => $publicId,
                'type_code' => $source->type_code,
                'status' => 'draft',
                'visibility' => 'hidden',
                'internal_code' => null,
                'brand_id' => $source->brand_id,
                'shipping_policy' => $source->shipping_policy,
                'return_policy' => $source->return_policy,
                'default_locale' => $source->default_locale,
                'is_featured' => false,
                'tags' => $source->tags,
                'published_at' => null,
                'archived_at' => null,
                'version' => 1,
                'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $translations = $this->database->table('store_product_translations')
                ->where('product_id', $source->id)
                ->get();
            foreach ($translations as $translation) {
                $this->database->table('store_product_translations')->insert([
                    'product_id' => $productId,
                    'locale' => $translation->locale,
                    'name' => 'Copy of '.$translation->name,
                    'slug' => $translation->slug.'-copy-'.strtolower(substr($publicId, -6)),
                    'short_description' => $translation->short_description,
                    'description' => $translation->description,
                    'meta_title' => $translation->meta_title,
                    'meta_description' => $translation->meta_description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $categories = $this->database->table('store_product_categories')
                ->where('product_id', $source->id)
                ->get();
            foreach ($categories as $category) {
                $this->database->table('store_product_categories')->insert([
                    'product_id' => $productId,
                    'category_id' => $category->category_id,
                    'is_primary' => $category->is_primary,
                    'position' => $category->position,
                    'created_at' => $now,
                ]);
            }

            $variantMap = [];
            $variants = $this->database->table('store_product_variants')
                ->where('product_id', $source->id)
                ->where('status', '!=', 'archived')
                ->orderBy('position')
                ->get();
            foreach ($variants as $variant) {
                $variantMap[(int) $variant->id] = (int) $this->database->table('store_product_variants')->insertGetId([
                    'public_id' => (string) $this->ids->generate(),
                    'product_id' => $productId,
                    'status' => 'inactive',
                    'sku' => null,
                    'barcode' => null,
                    'is_default' => $variant->is_default,
                    'combination_key' => $variant->combination_key,
                    'position' => $variant->position,
                    'weight_minor' => $variant->weight_minor,
                    'weight_unit' => $variant->weight_unit,
                    'version' => 1,
                    'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
                    'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'archived_at' => null,
                ]);
            }

            if ($variantMap !== []) {
                $options = $this->database->table('store_variant_option_values')
                    ->whereIn('variant_id', array_keys($variantMap))
                    ->get();
                foreach ($options as $option) {
                    $this->database->table('store_variant_option_values')->insert([
                        'variant_id' => $variantMap[(int) $option->variant_id],
                        'attribute_id' => $option->attribute_id,
                        'attribute_option_id' => $option->attribute_option_id,
                        'created_at' => $now,
                    ]);
                }
            }

            $media = $this->database->table('store_product_media')
                ->where('product_id', $source->id)
                ->whereNull('variant_id')
                ->get();
            foreach ($media as $item) {
                $this->database->table('store_product_media')->insert([
                    'public_id' => (string) $this->ids->generate(),
                    'product_id' => $productId,
                    'variant_id' => null,
                    'media_asset_id' => $item->media_asset_id,
                    'external_source' => $item->external_source,
                    'external_key' => $item->external_key,
                    'role' => $item->role,
                    'is_primary' => $item->is_primary,
                    'position' => $item->position,
                    'visibility' => $item->visibility,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $publicId;
        });
    }
}
