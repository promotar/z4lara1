<?php

namespace Modules\Store\Infrastructure\Integrations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableGateway;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableRequest;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableResult;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableStatus;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Throwable;

final readonly class DatabaseExternalSellableGateway implements ExternalSellableGateway
{
    public function __construct(private PublicIdGenerator $ids) {}

    public function upsert(ExternalSellableRequest $request): ExternalSellableResult
    {
        try {
            $this->validateRequest($request);

            return DB::transaction(function () use ($request): ExternalSellableResult {
                if ($prior = $this->operation($request->ownerCode, $request->idempotencyKey)) {
                    return $prior;
                }

                $link = DB::table('store_external_sellables')
                    ->where('owner_code', $request->ownerCode)
                    ->where('external_id', $request->externalId)
                    ->lockForUpdate()->first();
                $now = now();
                $payloadHash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));

                if ($link === null) {
                    [$productId, $variantId] = $this->createSellable($request, $now);
                    DB::table('store_external_sellables')->insert([
                        'owner_code' => $request->ownerCode,
                        'external_id' => $request->externalId,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'source_revision' => $request->sourceRevision,
                        'state' => 'published',
                        'payload_hash' => $payloadHash,
                        'metadata' => json_encode($request->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $this->updateSellable($link, $request, $now);
                    DB::table('store_external_sellables')->where('id', $link->id)->update([
                        'source_revision' => $request->sourceRevision,
                        'state' => 'published',
                        'payload_hash' => $payloadHash,
                        'metadata' => json_encode($request->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                        'last_failure' => null,
                        'updated_at' => $now,
                    ]);
                }

                $result = $this->resultFor($request->ownerCode, $request->externalId, 'published');
                $this->recordOperation($request->ownerCode, $request->externalId, $request->idempotencyKey, 'upsert', $result);

                return $result;
            }, 3);
        } catch (InvalidArgumentException $exception) {
            return new ExternalSellableResult(false, 'rejected', failureCode: 'validation_failed', message: $exception->getMessage());
        } catch (QueryException $exception) {
            return new ExternalSellableResult(false, 'failed', failureCode: 'conflict', message: 'Store rejected a conflicting external sellable request.');
        } catch (Throwable $exception) {
            report($exception);

            return new ExternalSellableResult(false, 'failed', failureCode: 'temporary_failure', message: 'Store could not process the external sellable request.');
        }
    }

    public function withdraw(string $ownerCode, string $externalId, string $reason, string $idempotencyKey): ExternalSellableResult
    {
        if (trim($reason) === '') {
            return new ExternalSellableResult(false, 'rejected', failureCode: 'validation_failed', message: 'Withdrawal requires a reason.');
        }

        return $this->changeAvailability($ownerCode, $externalId, 'archived', 0, $idempotencyKey, 'withdraw');
    }

    public function syncAvailability(string $ownerCode, string $externalId, string $state, int $quantity, string $idempotencyKey): ExternalSellableResult
    {
        if (! in_array($state, ['available', 'unavailable', 'archived'], true) || $quantity < 0) {
            return new ExternalSellableResult(false, 'rejected', failureCode: 'validation_failed', message: 'Availability state or quantity is invalid.');
        }

        return $this->changeAvailability($ownerCode, $externalId, $state, $quantity, $idempotencyKey, 'availability');
    }

    public function reconcile(string $ownerCode, string $externalId): ExternalSellableResult
    {
        $link = $this->link($ownerCode, $externalId);
        if ($link === null) {
            return new ExternalSellableResult(false, 'missing', failureCode: 'not_found', message: 'External sellable link was not found.', drift: ['link_missing']);
        }

        $drift = [];
        $product = DB::table('store_products')->where('id', $link->product_id)->first();
        $variant = DB::table('store_product_variants')->where('id', $link->variant_id)->first();
        $inventory = $variant ? DB::table('store_inventory_items')->where('variant_id', $variant->id)->first() : null;
        $price = $variant ? DB::table('store_prices')->where('variant_id', $variant->id)->where('type', 'regular')->where('status', 'active')->first() : null;
        if (! $product) {
            $drift[] = 'product_missing';
        }
        if (! $variant) {
            $drift[] = 'variant_missing';
        }
        if (! $inventory) {
            $drift[] = 'inventory_missing';
        }
        if (! $price) {
            $drift[] = 'price_missing';
        }
        if ($product && $link->state === 'published' && ($product->status !== 'published' || $product->visibility !== 'public')) {
            $drift[] = 'publication_state_mismatch';
        }

        $result = $this->resultFor($ownerCode, $externalId, $drift === [] ? (string) $link->state : 'drifted', $drift);

        return new ExternalSellableResult(
            $drift === [], $result->state, $result->productPublicId, $result->variantPublicId,
            $result->inventoryPublicId, $drift === [] ? null : 'drift_detected',
            $drift === [] ? null : 'Store reconciliation detected external sellable drift.', $drift,
        );
    }

    public function status(string $ownerCode, string $externalId): ExternalSellableStatus
    {
        $row = DB::table('store_external_sellables as link')
            ->join('store_products as product', 'product.id', '=', 'link.product_id')
            ->join('store_product_variants as variant', 'variant.id', '=', 'link.variant_id')
            ->leftJoin('store_inventory_items as inventory', 'inventory.variant_id', '=', 'variant.id')
            ->leftJoin('store_prices as price', function ($join): void {
                $join->on('price.variant_id', '=', 'variant.id')
                    ->where('price.type', '=', 'regular')
                    ->where('price.status', '=', 'active');
            })
            ->where('link.owner_code', $ownerCode)
            ->where('link.external_id', $externalId)
            ->first([
                'link.state as sellable_state', 'link.metadata',
                'product.status as product_status', 'product.visibility as product_visibility',
                'variant.status as variant_status', 'price.amount_minor', 'price.currency',
                'inventory.tracking_mode', 'inventory.on_hand', 'inventory.reserved',
                'inventory.safety_stock', 'inventory.allow_backorder',
            ]);

        if ($row === null) {
            return new ExternalSellableStatus(false, false, message: 'External sellable link was not found.');
        }

        $available = $row->tracking_mode === 'tracked'
            ? max(0, (int) $row->on_hand - (int) $row->reserved - (int) $row->safety_stock)
            : 99;
        $metadata = json_decode((string) ($row->metadata ?? '{}'), true) ?: [];
        $isOriginal = ($metadata['edition_type'] ?? null) === 'original';
        $maximum = $isOriginal ? min(1, $available) : min(99, $available);
        $inventoryAllowsPurchase = $row->tracking_mode !== 'tracked'
            || ($isOriginal ? $available === 1 : ($available > 0 || (bool) $row->allow_backorder));
        $purchasable = in_array((string) $row->sellable_state, ['published', 'available'], true)
            && $row->product_status === 'published'
            && $row->product_visibility === 'public'
            && $row->variant_status === 'active'
            && $row->amount_minor !== null
            && $inventoryAllowsPurchase;

        return new ExternalSellableStatus(
            true,
            $purchasable,
            $row->amount_minor === null ? null : (int) $row->amount_minor,
            $row->currency === null ? null : (string) $row->currency,
            $available,
            $purchasable ? max(1, $maximum) : 0,
            (string) $row->product_status,
            (string) $row->product_visibility,
            (string) $row->variant_status,
            (string) $row->sellable_state,
            $purchasable ? null : 'Store listing is not currently purchasable.',
        );
    }

    private function changeAvailability(string $ownerCode, string $externalId, string $state, int $quantity, string $key, string $action): ExternalSellableResult
    {
        try {
            return DB::transaction(function () use ($ownerCode, $externalId, $state, $quantity, $key, $action): ExternalSellableResult {
                if ($prior = $this->operation($ownerCode, $key)) {
                    return $prior;
                }
                $link = DB::table('store_external_sellables')->where('owner_code', $ownerCode)->where('external_id', $externalId)->lockForUpdate()->first();
                if (! $link) {
                    return new ExternalSellableResult(false, 'missing', failureCode: 'not_found', message: 'External sellable link was not found.');
                }
                $inventory = DB::table('store_inventory_items')->where('variant_id', $link->variant_id)->lockForUpdate()->first();
                if (! $inventory) {
                    return new ExternalSellableResult(false, 'failed', failureCode: 'inventory_missing', message: 'Store inventory is missing.');
                }
                if ($quantity < (int) $inventory->reserved) {
                    return new ExternalSellableResult(false, 'failed', failureCode: 'reserved_conflict', message: 'Quantity cannot be reduced below reserved inventory.');
                }
                $now = now();
                DB::table('store_inventory_items')->where('id', $inventory->id)->update(['on_hand' => $state === 'available' ? $quantity : 0, 'version' => DB::raw('version + 1'), 'updated_at' => $now]);
                DB::table('store_products')->where('id', $link->product_id)->update([
                    'status' => $state === 'archived' ? 'archived' : 'published',
                    'visibility' => $state === 'available' ? 'public' : 'hidden',
                    'archived_at' => $state === 'archived' ? $now : null,
                    'version' => DB::raw('version + 1'), 'updated_at' => $now,
                ]);
                DB::table('store_external_sellables')->where('id', $link->id)->update(['state' => $state, 'updated_at' => $now]);
                $result = $this->resultFor($ownerCode, $externalId, $state);
                $this->recordOperation($ownerCode, $externalId, $key, $action, $result);

                return $result;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            return new ExternalSellableResult(false, 'failed', failureCode: 'temporary_failure', message: 'Store availability synchronization failed.');
        }
    }

    private function createSellable(ExternalSellableRequest $request, mixed $now): array
    {
        $productPublicId = (string) $this->ids->generate();
        $variantPublicId = (string) $this->ids->generate();
        $productId = DB::table('store_products')->insertGetId([
            'public_id' => $productPublicId, 'type_code' => 'simple', 'status' => 'published', 'visibility' => 'public',
            'internal_code' => 'external-'.substr(hash('sha256', $request->ownerCode.'|'.$request->externalId), 0, 32),
            'default_locale' => $request->locale, 'is_featured' => false, 'published_at' => $now, 'version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $variantId = DB::table('store_product_variants')->insertGetId([
            'public_id' => $variantPublicId, 'product_id' => $productId, 'status' => 'active',
            'sku' => 'EXT-'.strtoupper(substr(hash('sha256', $request->ownerCode.'|'.$request->externalId), 0, 20)),
            'is_default' => true, 'combination_key' => '', 'position' => 0, 'version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->writeDetails($productId, $variantId, $request, $now, true);

        return [(int) $productId, (int) $variantId];
    }

    private function updateSellable(object $link, ExternalSellableRequest $request, mixed $now): void
    {
        DB::table('store_products')->where('id', $link->product_id)->update([
            'status' => 'published', 'visibility' => 'public', 'published_at' => DB::raw('COALESCE(published_at, CURRENT_TIMESTAMP)'),
            'archived_at' => null, 'version' => DB::raw('version + 1'), 'updated_at' => $now,
        ]);
        DB::table('store_product_variants')->where('id', $link->variant_id)->update(['status' => 'active', 'archived_at' => null, 'version' => DB::raw('version + 1'), 'updated_at' => $now]);
        $this->writeDetails((int) $link->product_id, (int) $link->variant_id, $request, $now, false);
    }

    private function writeDetails(int $productId, int $variantId, ExternalSellableRequest $request, mixed $now, bool $create): void
    {
        $translation = ['name' => trim($request->title), 'slug' => $request->slug, 'short_description' => $request->shortDescription, 'description' => $request->description, 'updated_at' => $now];
        if ($create) {
            DB::table('store_product_translations')->insert($translation + ['product_id' => $productId, 'locale' => $request->locale, 'created_at' => $now]);
        } else {
            DB::table('store_product_translations')->updateOrInsert(['product_id' => $productId, 'locale' => $request->locale], $translation + ['created_at' => $now]);
        }

        DB::table('store_prices')->where('variant_id', $variantId)->where('type', 'regular')->where('status', 'active')->update(['status' => 'archived', 'archived_at' => $now]);
        DB::table('store_prices')->insert(['public_id' => (string) $this->ids->generate(), 'variant_id' => $variantId, 'type' => 'regular', 'amount_minor' => $request->priceMinor, 'currency' => strtoupper($request->currency), 'priority' => 0, 'status' => 'active', 'created_at' => $now]);

        $inventory = DB::table('store_inventory_items')->where('variant_id', $variantId)->lockForUpdate()->first();
        if ($inventory && $request->quantity < (int) $inventory->reserved) {
            throw new InvalidArgumentException('Quantity cannot be below reserved inventory.');
        }
        if ($inventory) {
            DB::table('store_inventory_items')->where('id', $inventory->id)->update(['on_hand' => $request->quantity, 'version' => DB::raw('version + 1'), 'updated_at' => $now]);
        } else {
            DB::table('store_inventory_items')->insert(['public_id' => (string) $this->ids->generate(), 'variant_id' => $variantId, 'tracking_mode' => 'tracked', 'on_hand' => $request->quantity, 'reserved' => 0, 'allow_backorder' => false, 'safety_stock' => 0, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('store_product_media')->where('product_id', $productId)->where('external_source', $request->ownerCode)->delete();
        foreach (array_values(array_unique($request->imageUrls)) as $position => $url) {
            DB::table('store_product_media')->insert(['public_id' => (string) $this->ids->generate(), 'product_id' => $productId, 'external_source' => $request->ownerCode, 'external_key' => $url, 'role' => $position === 0 ? 'cover' : 'gallery', 'is_primary' => $position === 0, 'position' => $position, 'visibility' => 'public', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function validateRequest(ExternalSellableRequest $request): void
    {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $request->ownerCode)) {
            throw new InvalidArgumentException('Owner code is invalid.');
        }
        if (trim($request->externalId) === '' || strlen($request->externalId) > 128) {
            throw new InvalidArgumentException('External identifier is invalid.');
        }
        if ($request->sourceRevision < 1 || trim($request->title) === '' || mb_strlen($request->title) > 255) {
            throw new InvalidArgumentException('Revision and title are required.');
        }
        if (! preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $request->locale)) {
            throw new InvalidArgumentException('Locale is invalid.');
        }
        if ((string) Str::slug($request->slug) !== $request->slug || $request->slug === '') {
            throw new InvalidArgumentException('Slug is invalid.');
        }
        if ($request->priceMinor < 0 || $request->quantity < 0 || ! preg_match('/^[A-Z]{3}$/', $request->currency)) {
            throw new InvalidArgumentException('Price, currency, or quantity is invalid.');
        }
        if (trim($request->idempotencyKey) === '' || strlen($request->idempotencyKey) > 191) {
            throw new InvalidArgumentException('Idempotency key is invalid.');
        }
        foreach ($request->imageUrls as $url) {
            if (! is_string($url) || strlen($url) > 255 || ! str_starts_with($url, '/')) {
                throw new InvalidArgumentException('External image URLs must be same-origin paths.');
            }
        }
        foreach (['source_module', 'artwork_public_id', 'artist_public_id', 'artist_display_name', 'artwork_title', 'artwork_type', 'edition_type', 'dimensions', 'frame_status', 'preparation_lead_time', 'certificate_status', 'main_image_display_path'] as $field) {
            if (! array_key_exists($field, $request->metadata)) {
                throw new InvalidArgumentException("External sellable metadata is missing {$field}.");
            }
        }
    }

    private function operation(string $ownerCode, string $key): ?ExternalSellableResult
    {
        $json = DB::table('store_external_sellable_operations')->where('owner_code', $ownerCode)->where('idempotency_key', $key)->value('response');
        if ($json === null) {
            return null;
        }
        $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

        return new ExternalSellableResult(...$data);
    }

    private function recordOperation(string $owner, string $external, string $key, string $action, ExternalSellableResult $result): void
    {
        DB::table('store_external_sellable_operations')->insert(['owner_code' => $owner, 'external_id' => $external, 'idempotency_key' => $key, 'action' => $action, 'response' => json_encode((array) $result, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function link(string $owner, string $external): ?object
    {
        return DB::table('store_external_sellables')->where('owner_code', $owner)->where('external_id', $external)->first();
    }

    private function resultFor(string $owner, string $external, string $state, array $drift = []): ExternalSellableResult
    {
        $row = DB::table('store_external_sellables as link')->join('store_products as product', 'product.id', '=', 'link.product_id')->join('store_product_variants as variant', 'variant.id', '=', 'link.variant_id')->leftJoin('store_inventory_items as inventory', 'inventory.variant_id', '=', 'variant.id')->where('link.owner_code', $owner)->where('link.external_id', $external)->first(['product.public_id as product_public_id', 'variant.public_id as variant_public_id', 'inventory.public_id as inventory_public_id']);

        return new ExternalSellableResult($row !== null && $drift === [], $state, $row?->product_public_id, $row?->variant_public_id, $row?->inventory_public_id, $drift === [] ? null : 'drift_detected', null, $drift);
    }
}
