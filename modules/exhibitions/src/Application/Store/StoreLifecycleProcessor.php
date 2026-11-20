<?php

namespace Modules\Exhibition\Application\Store;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleRecord;
use Throwable;

final readonly class StoreLifecycleProcessor
{
    public function __construct(private StoreSaleLifecycleService $sales) {}

    public function process(StoreLifecycleRecord $record): bool
    {
        if ($record->ownerCode !== 'exhibitions') {
            return true;
        }

        if (! Schema::hasTable('exhibition_store_lifecycle_receipts')) {
            return false;
        }

        $existing = DB::table('exhibition_store_lifecycle_receipts')->where('store_sequence', $record->sequence)->first();

        if ($existing?->state === 'processed') {
            return true;
        }

        try {
            DB::transaction(function () use ($record): void {
                $this->sales->apply($record);
                DB::table('exhibition_store_lifecycle_receipts')->updateOrInsert(
                    ['store_sequence' => $record->sequence],
                    [
                        'idempotency_key' => $record->idempotencyKey,
                        'artwork_public_id' => $record->externalId,
                        'store_order_public_id' => $record->orderPublicId,
                        'store_order_item_public_id' => $record->orderItemPublicId,
                        'action' => $record->action,
                        'quantity' => $record->quantity,
                        'state' => 'processed',
                        'payload_sha256' => hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)),
                        'failure_message' => null,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }, 3);

            return true;
        } catch (Throwable $exception) {
            DB::table('exhibition_store_lifecycle_receipts')->updateOrInsert(
                ['store_sequence' => $record->sequence],
                [
                    'idempotency_key' => $record->idempotencyKey,
                    'artwork_public_id' => $record->externalId,
                    'store_order_public_id' => $record->orderPublicId,
                    'store_order_item_public_id' => $record->orderItemPublicId,
                    'action' => $record->action,
                    'quantity' => $record->quantity,
                    'state' => 'failed',
                    'payload_sha256' => hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)),
                    'failure_message' => mb_substr(strip_tags($exception->getMessage()), 0, 1000),
                    'processed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            return false;
        }
    }
}
