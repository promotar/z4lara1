<?php

namespace Modules\Store\Infrastructure\Persistence\Media;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Media\MediaSource;
use Modules\Store\Domain\Media\MediaUploadCandidate;
use Modules\Store\Domain\Media\ProductMediaRepository;
use Modules\Store\Domain\Shared\ActorContext;

final class DatabaseProductMediaRepository implements ProductMediaRepository
{
    public function createAsset(
        string $publicId,
        string $disk,
        string $path,
        MediaUploadCandidate $candidate,
        string $checksum,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): void {
        DB::table('store_media_assets')->insert([
            'public_id' => $publicId,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $candidate->originalFilename,
            'mime_type' => $candidate->detectedMimeType,
            'size_bytes' => $candidate->sizeBytes,
            'checksum' => $checksum,
            'width' => $candidate->width,
            'height' => $candidate->height,
            'status' => 'ready',
            'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function variantBelongsToProduct(string $variantPublicId, string $productPublicId): bool
    {
        return DB::table('store_product_variants as variants')
            ->join('store_products as products', 'products.id', '=', 'variants.product_id')
            ->where('variants.public_id', $variantPublicId)
            ->where('products.public_id', $productPublicId)
            ->exists();
    }

    public function associationBelongsToProduct(string $associationPublicId, string $productPublicId): bool
    {
        return DB::table('store_product_media as media')
            ->join('store_products as products', 'products.id', '=', 'media.product_id')
            ->where('media.public_id', $associationPublicId)
            ->where('products.public_id', $productPublicId)
            ->exists();
    }

    public function attach(
        string $associationPublicId,
        string $productPublicId,
        ?string $variantPublicId,
        MediaSource $source,
        MediaRole $role,
        bool $primary,
        int $position,
        DateTimeImmutable $now,
    ): void {
        $productId = DB::table('store_products')->where('public_id', $productPublicId)->value('id')
            ?? throw new InvalidArgumentException('Product does not exist.');
        $variantId = $variantPublicId === null
            ? null
            : DB::table('store_product_variants')->where('public_id', $variantPublicId)->value('id');
        $assetId = $source->assetPublicId === null
            ? null
            : DB::table('store_media_assets')->where('public_id', $source->assetPublicId)->value('id');

        if ($source->assetPublicId !== null && $assetId === null) {
            throw new InvalidArgumentException('Store media asset does not exist.');
        }

        if ($primary && $role === MediaRole::Cover) {
            DB::table('store_product_media')
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->where('role', MediaRole::Cover->value)
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => $now]);
        }

        DB::table('store_product_media')->insert([
            'public_id' => $associationPublicId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'media_asset_id' => $assetId,
            'external_source' => $source->externalSource,
            'external_key' => $source->externalKey,
            'role' => $role->value,
            'is_primary' => $primary,
            'position' => $position,
            'visibility' => 'public',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function removeAssociation(string $associationPublicId): ?int
    {
        $row = DB::table('store_product_media')->where('public_id', $associationPublicId)->lockForUpdate()->first();
        if ($row === null) {
            return null;
        }

        DB::table('store_product_media')->where('id', $row->id)->delete();

        return $row->media_asset_id === null ? null : (int) $row->media_asset_id;
    }

    public function addAssociationTranslation(
        string $associationPublicId,
        string $locale,
        ?string $altText,
        ?string $title,
        ?string $caption,
        DateTimeImmutable $now,
    ): void {
        $associationId = DB::table('store_product_media')->where('public_id', $associationPublicId)->value('id')
            ?? throw new InvalidArgumentException('Product media association does not exist.');
        DB::table('store_product_media_translations')->insert([
            'product_media_id' => $associationId,
            'locale' => $locale,
            'alt_text' => $altText,
            'title' => $title,
            'caption' => $caption,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function assetReferenceCount(int $assetId): int
    {
        return DB::table('store_product_media')->where('media_asset_id', $assetId)->count();
    }

    public function reorderAssociations(
        string $productPublicId,
        array $associationPublicIds,
        DateTimeImmutable $now,
    ): void {
        $productId = DB::table('store_products')->where('public_id', $productPublicId)->value('id')
            ?? throw new InvalidArgumentException('Product does not exist.');
        $owned = DB::table('store_product_media')
            ->where('product_id', $productId)
            ->whereIn('public_id', $associationPublicIds)
            ->pluck('public_id')
            ->all();
        if (count($owned) !== count(array_unique($associationPublicIds))) {
            throw new InvalidArgumentException('Every media item must belong to the selected product.');
        }

        foreach (array_values($associationPublicIds) as $position => $publicId) {
            DB::table('store_product_media')
                ->where('product_id', $productId)
                ->where('public_id', $publicId)
                ->update(['position' => $position, 'updated_at' => $now]);
        }
    }
}
