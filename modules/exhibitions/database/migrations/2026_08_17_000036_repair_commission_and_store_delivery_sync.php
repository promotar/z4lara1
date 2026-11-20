<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairLegacyZeroCommissionSnapshots();
        $this->synchronizeDeliveredStoreOrders();
    }

    public function down(): void
    {
        // Financial and fulfillment history is append-only. A rollback must
        // never silently rewrite settlements or delivery evidence.
    }

    private function repairLegacyZeroCommissionSnapshots(): void
    {
        if (! Schema::hasTable('exhibition_settlements') || ! Schema::hasTable('exhibition_fulfillments')) {
            return;
        }

        $basisPoints = $this->defaultCommissionBasisPoints();
        if ($basisPoints < 1) {
            return;
        }

        $rows = DB::table('exhibition_settlements as settlement')
            ->join('exhibition_fulfillments as fulfillment', 'fulfillment.id', '=', 'settlement.fulfillment_id')
            ->whereNotIn('settlement.state', ['paid', 'reversed'])
            ->select('settlement.*', 'fulfillment.commission_basis_points_snapshot', 'fulfillment.commission_source_snapshot')
            ->orderBy('settlement.id')
            ->get();

        foreach ($rows as $row) {
            $rules = json_decode((string) $row->rules_snapshot, true) ?: [];
            if (($rules['commission_source'] ?? null) !== 'artist_agreement' || (int) ($rules['commission_basis_points'] ?? -1) !== 0) {
                continue;
            }

            $base = (int) ($rules['commission_base_minor'] ?? max(0, (int) $row->sale_price_minor - (int) $row->order_discount_minor));
            $commission = intdiv($base * $basisPoints, 10_000);
            $delta = $commission - (int) $row->platform_commission_minor;
            if ($delta <= 0 || (int) $row->artist_net_minor < $delta) {
                continue;
            }

            $rules['commission_basis_points'] = $basisPoints;
            $rules['commission_source'] = 'default';
            $rules['commission_base_minor'] = $base;
            $rules['legacy_zero_agreement_repaired_at'] = now()->toIso8601String();

            DB::transaction(function () use ($row, $rules, $basisPoints, $commission, $delta): void {
                DB::table('exhibition_settlements')->where('id', $row->id)->update([
                    'rules_snapshot' => json_encode($rules, JSON_THROW_ON_ERROR),
                    'platform_commission_minor' => $commission,
                    'artist_net_minor' => (int) $row->artist_net_minor - $delta,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);

                DB::table('exhibition_fulfillments')->where('id', $row->fulfillment_id)->update([
                    'commission_basis_points_snapshot' => $basisPoints,
                    'commission_source_snapshot' => 'default',
                    'updated_at' => now(),
                ]);

                $key = 'migration:legacy-zero-commission:'.$row->id;
                DB::table('exhibition_settlement_ledger_entries')->insertOrIgnore([
                    ['public_id' => (string) Str::uuid(), 'settlement_id' => $row->id, 'entry_type' => 'platform_commission_correction', 'direction' => 'debit', 'amount_minor' => $delta, 'currency' => $row->currency, 'idempotency_key' => $key.':commission', 'reference' => null, 'description' => 'Applied the configured platform commission after repairing the legacy unspecified agreement rate.', 'actor_user_id' => null, 'occurred_at' => now(), 'created_at' => now()],
                    ['public_id' => (string) Str::uuid(), 'settlement_id' => $row->id, 'entry_type' => 'artist_net_correction', 'direction' => 'credit', 'amount_minor' => $delta, 'currency' => $row->currency, 'idempotency_key' => $key.':artist-net', 'reference' => null, 'description' => 'Reduced the artist liability by the corrected platform commission.', 'actor_user_id' => null, 'occurred_at' => now(), 'created_at' => now()],
                ]);
                DB::table('exhibition_settlement_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(), 'settlement_id' => $row->id, 'actor_user_id' => null,
                    'action' => 'commission_snapshot_repaired', 'from_state' => $row->state, 'to_state' => $row->state,
                    'idempotency_key' => $key.':event', 'reason' => 'Replaced a legacy unspecified zero agreement rate with the configured Commission & Earnings default.',
                    'metadata' => json_encode(['commission_basis_points' => $basisPoints, 'commission_delta_minor' => $delta], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }, 3);
        }
    }

    private function synchronizeDeliveredStoreOrders(): void
    {
        if (! Schema::hasTable('store_orders') || ! Schema::hasTable('exhibition_fulfillments') || ! Schema::hasTable('exhibition_settlements')) {
            return;
        }

        $returnDays = $this->settingInteger('exhibitions.settlement.return_window_days', 14);
        if ($this->settingValue('exhibitions.settlement.eligibility_trigger') === 'delivered') {
            $returnDays = 0;
        }

        $rows = DB::table('exhibition_fulfillments as fulfillment')
            ->join('store_orders as store_order', 'store_order.public_id', '=', 'fulfillment.store_order_public_id')
            ->leftJoin('exhibition_settlements as settlement', 'settlement.fulfillment_id', '=', 'fulfillment.id')
            ->where('store_order.status', 'delivered')
            ->whereNotIn('fulfillment.state', ['delivered', 'return_requested', 'returned', 'cancelled'])
            ->select(
                'fulfillment.id as fulfillment_id', 'fulfillment.state as fulfillment_state',
                'store_order.public_id as order_public_id', 'store_order.delivered_at', 'store_order.updated_at as order_updated_at',
                'settlement.id as settlement_id', 'settlement.state as settlement_state'
            )
            ->orderBy('fulfillment.id')
            ->get();

        foreach ($rows as $row) {
            $deliveredAt = Carbon::parse($row->delivered_at ?? $row->order_updated_at ?? now());
            $key = 'migration:store-delivered:'.$row->fulfillment_id;

            DB::transaction(function () use ($row, $deliveredAt, $returnDays, $key): void {
                DB::table('exhibition_fulfillments')->where('id', $row->fulfillment_id)->update([
                    'state' => 'delivered', 'delivered_at' => $deliveredAt, 'custody_holder' => 'customer',
                    'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
                ]);
                DB::table('exhibition_fulfillment_events')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(), 'fulfillment_id' => $row->fulfillment_id, 'actor_user_id' => null,
                    'actor_role' => 'store', 'action' => 'store_delivery_synchronized', 'from_state' => $row->fulfillment_state, 'to_state' => 'delivered',
                    'idempotency_key' => $key.':fulfillment', 'notes' => 'Existing delivered Store order synchronized during the Exhibitions module update.',
                    'metadata' => json_encode(['store_order_public_id' => $row->order_public_id], JSON_THROW_ON_ERROR),
                    'occurred_at' => $deliveredAt, 'created_at' => now(), 'updated_at' => now(),
                ]);

                if ($row->settlement_id !== null && $row->settlement_state === 'awaiting_delivery') {
                    DB::table('exhibition_settlements')->where('id', $row->settlement_id)->update([
                        'state' => 'return_window', 'return_window_ends_at' => $deliveredAt->copy()->addDays(max(0, $returnDays)),
                        'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
                    ]);
                    DB::table('exhibition_settlement_events')->insertOrIgnore([
                        'public_id' => (string) Str::uuid(), 'settlement_id' => $row->settlement_id, 'actor_user_id' => null,
                        'action' => 'delivery_confirmed', 'from_state' => 'awaiting_delivery', 'to_state' => 'return_window',
                        'idempotency_key' => $key.':settlement', 'reason' => 'Store delivery synchronized automatically; customer return period started.',
                        'metadata' => json_encode(['return_window_days' => $returnDays], JSON_THROW_ON_ERROR),
                        'occurred_at' => $deliveredAt, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }, 3);
        }
    }

    private function defaultCommissionBasisPoints(): int
    {
        $rate = (string) ($this->settingValue('exhibitions.commission.default_rate') ?? '20.000');
        if (preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', trim($rate), $parts) !== 1) {
            return 2000;
        }

        return intdiv(((int) $parts[1] * 1000) + (int) str_pad($parts[2] ?? '', 3, '0') + 5, 10);
    }

    private function settingInteger(string $key, int $fallback): int
    {
        $value = $this->settingValue($key);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function settingValue(string $key): mixed
    {
        if (! Schema::hasTable('exhibition_settings')) {
            return null;
        }
        $value = DB::table('exhibition_settings')->where('key', $key)->value('value');

        return $value === null ? null : json_decode((string) $value, true);
    }
};
