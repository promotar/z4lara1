<?php

namespace Modules\Exhibition\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\Store\StoreLifecycleProcessor;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleGateway;
use Throwable;

final class ReconcileStoreLifecycle extends Command
{
    protected $signature = 'exhibitions:store-lifecycle:reconcile {--limit=100}';

    protected $description = 'Consume the durable Store external-sellable lifecycle feed.';

    public function handle(StoreLifecycleGateway $gateway, StoreLifecycleProcessor $processor): int
    {
        $key = 'store.external-sellable.lifecycle.v1';

        if (
            ! Schema::hasTable('exhibition_integration_cursors')
            || ! Schema::hasTable('exhibition_store_lifecycle_receipts')
        ) {
            $this->error('Exhibitions lifecycle tables are not migrated; reconciliation was skipped safely.');

            return self::FAILURE;
        }

        $sequence = 0;

        try {
            $cursor = DB::table('exhibition_integration_cursors')->where('integration_key', $key)->first();
            $sequence = (int) ($cursor?->last_sequence ?? 0);
            $limit = min(500, max(1, (int) $this->option('limit')));

            if (! $gateway->available()) {
                $this->recordCursor($key, $sequence, 'Store lifecycle feed is unavailable.');
                $this->error('Store lifecycle feed is unavailable.');

                return self::FAILURE;
            }

            foreach ($gateway->after($sequence, $limit) as $record) {
                if (! $processor->process($record)) {
                    $this->recordCursor(
                        $key,
                        $sequence,
                        'Store lifecycle sequence '.$record->sequence.' failed and will be retried.',
                    );

                    return self::FAILURE;
                }

                $sequence = $record->sequence;
                $this->recordCursor($key, $sequence, null);
            }

            $this->info('Store lifecycle reconciled through sequence '.$sequence.'.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $message = mb_substr(strip_tags($exception->getMessage()), 0, 1000);
            $this->recordCursor($key, $sequence, $message);
            $this->error($message);

            return self::FAILURE;
        }
    }

    private function recordCursor(string $key, int $sequence, ?string $error): void
    {
        DB::table('exhibition_integration_cursors')->updateOrInsert(
            ['integration_key' => $key],
            [
                'last_sequence' => $sequence,
                'last_processed_at' => $error === null ? now() : null,
                'last_error' => $error,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
