<?php

namespace Modules\Exhibition\Application\Finance;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;

final class ArtistSettlementService
{
    private const COMPONENTS = ['sale_price', 'order_discount', 'platform_commission', 'payment_cost', 'packaging_cost', 'shipping_cost', 'insurance', 'tax', 'refund', 'adjustment'];

    public function __construct(private readonly ExhibitionNotificationService $notifications) {}

    public function create(string $fulfillmentPublicId, array $amounts, array $rules, string $idempotencyKey): object
    {
        $existing = DB::table('exhibition_settlement_events')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->findById($existing->settlement_id);
        }
        $fulfillment = DB::table('exhibition_fulfillments')->where('public_id', $fulfillmentPublicId)->first() ?? abort(404);
        $currency = strtoupper((string) ($amounts['currency'] ?? 'JOD'));
        $scale = (int) ($amounts['scale'] ?? 3);
        if (! preg_match('/^[A-Z]{3}$/', $currency) || $scale < 0 || $scale > 4) {
            throw new DomainException('A valid currency and monetary scale are required.');
        }
        $values = [];
        foreach (self::COMPONENTS as $name) {
            $values[$name] = $this->minor($amounts[$name.'_minor'] ?? 0, $name === 'adjustment');
        }
        // Shipping and tax belong to the Store order, not to the artist's
        // commission base or earnings. They remain snapshotted for reference
        // but never reduce the artist settlement.
        $costs = array_sum(array_intersect_key($values, array_flip(['order_discount', 'platform_commission', 'payment_cost', 'packaging_cost', 'insurance', 'refund'])));
        $net = $values['sale_price'] - $costs + $values['adjustment'];
        if ($net < 0) {
            throw new DomainException('Artist net cannot be negative.');
        }

        return DB::transaction(function () use ($fulfillment, $currency, $scale, $rules, $values, $net, $idempotencyKey): object {
            if ($row = DB::table('exhibition_settlements')->where('fulfillment_id', $fulfillment->id)->first()) {
                return $row;
            }
            $id = DB::table('exhibition_settlements')->insertGetId([
                'public_id' => (string) Str::uuid(), 'fulfillment_id' => $fulfillment->id, 'artist_user_id' => $fulfillment->artist_user_id, 'state' => 'pending_sale', 'currency' => $currency, 'scale' => $scale,
                'rules_snapshot' => json_encode($rules, JSON_THROW_ON_ERROR), 'sale_price_minor' => $values['sale_price'], 'order_discount_minor' => $values['order_discount'], 'platform_commission_minor' => $values['platform_commission'],
                'payment_cost_minor' => $values['payment_cost'], 'packaging_cost_minor' => $values['packaging_cost'], 'shipping_cost_minor' => $values['shipping_cost'], 'insurance_minor' => $values['insurance'], 'tax_minor' => $values['tax'],
                'refund_minor' => $values['refund'], 'adjustment_minor' => $values['adjustment'], 'artist_net_minor' => $net, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->ledger($id, 'sale_price', 'credit', $values['sale_price'], $currency, $idempotencyKey.':sale', 'Gross artwork order value; payout remains gated until payment, delivery, and return-window completion.');
            foreach (['order_discount', 'platform_commission', 'payment_cost', 'packaging_cost', 'insurance', 'refund'] as $name) {
                if ($values[$name] > 0) {
                    $this->ledger($id, $name, 'debit', $values[$name], $currency, $idempotencyKey.':'.$name, ucwords(str_replace('_', ' ', $name)).'.');
                }
            }
            if ($values['adjustment'] !== 0) {
                $this->ledger($id, 'approved_adjustment', $values['adjustment'] > 0 ? 'credit' : 'debit', abs($values['adjustment']), $currency, $idempotencyKey.':adjustment', 'Approved manual adjustment.');
            }
            $this->ledger($id, 'artist_net', 'debit', $net, $currency, $idempotencyKey.':net', 'Artist net settlement liability.');
            $this->event($id, null, 'pending_sale', 'settlement_created', $idempotencyKey, 'Pending sale recorded and financial rules snapshotted; payout is not yet eligible.');

            return $this->findById($id);
        }, 3);
    }

    public function collected(string $publicId, string $reference, string $key): object
    {
        return $this->transition($publicId, ['pending_sale'], 'awaiting_delivery', 'payment_collected', $key, 'Order value collection confirmed.', null, ['payment_collected_at' => now()], ['collection_reference' => $reference]);
    }

    public function beginReturnWindow(string $publicId, int $days, string $key): object
    {
        $row = $this->find($publicId);
        $f = DB::table('exhibition_fulfillments')->where('id', $row->fulfillment_id)->first();
        if (! $f?->delivered_at || $f->state !== 'delivered') {
            throw new DomainException('Delivered fulfillment confirmation is required.');
        }

        return $this->transition($publicId, ['awaiting_delivery'], 'return_window', 'delivery_confirmed', $key, 'Delivery confirmed; return window started.', null, ['return_window_ends_at' => now()->addDays(max(0, $days))]);
    }

    public function reviewEligibility(string $publicId, int $actor, string $key): object
    {
        $row = $this->find($publicId);
        $this->assertGates($row, false);

        return $this->transition($publicId, ['return_window'], 'under_review', 'eligibility_reviewed', $key, 'Collection, delivery, return-window, and dispute gates passed.', $actor);
    }

    public function approve(string $publicId, int $actor, string $reason, string $key): object
    {
        if (trim($reason) === '') {
            throw new DomainException('Finance approval reason is required.');
        }
        $row = $this->find($publicId);
        $this->assertGates($row, false);

        return $this->transition($publicId, ['under_review', 'payout_requested'], 'approved', 'finance_approved', $key, $reason, $actor, ['finance_approved_at' => now(), 'finance_approved_by' => $actor]);
    }

    public function markEligible(string $publicId, int $actor, string $key): object
    {
        $row = $this->find($publicId);
        $this->assertGates($row, true);
        $result = $this->transition($publicId, ['approved'], 'eligible_for_settlement', 'settlement_eligible', $key, 'All settlement gates and finance approval passed.', $actor);
        $this->notifications->dispatchCommitted('settlement_eligibility', [$result->artist_user_id], ['message' => 'Your settlement passed the eligibility gates.', 'link' => '/exhibitions/dashboard/earnings/'.$result->public_id], 'settlement-eligible:'.$key);

        return $result;
    }

    public function requestPayout(string $publicId, int $artistUserId, string $key): object
    {
        $row = $this->find($publicId);
        if ((int) $row->artist_user_id !== $artistUserId) {
            abort(404);
        }
        $this->assertGates($row, false);
        $this->verifiedPayoutProfile($row);

        $result = $this->transition(
            $publicId,
            ['return_window', 'eligible_for_settlement'],
            'payout_requested',
            'payout_requested',
            $key,
            'Artist requested payment of available earnings.',
            $artistUserId,
        );
        $this->notifications->dispatchCommitted(
            'payout_requested',
            [$artistUserId],
            ['message' => 'Your payout request was submitted for administration review.', 'link' => '/exhibitions/dashboard/earnings/'.$result->public_id],
            'payout-requested:'.$key,
        );

        return $result;
    }

    public function declinePayoutRequest(string $publicId, int $actor, string $reason, string $key): object
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required when returning a payout request to the artist.');
        }
        $result = $this->transition($publicId, ['payout_requested'], 'eligible_for_settlement', 'payout_request_declined', $key, $reason, $actor);
        $this->notifications->dispatchCommitted(
            'payout_request_declined',
            [$result->artist_user_id],
            ['message' => 'Your payout request needs attention before it can be approved.', 'link' => '/exhibitions/dashboard/earnings/'.$result->public_id],
            'payout-request-declined:'.$key,
        );

        return $result;
    }

    public function schedulePayout(string $publicId, int $actor, string $provider, string $method, string $key): object
    {
        $provider = trim($provider);
        $method = trim($method);
        $key = trim($key);
        if ($provider === '' || $method === '' || $key === '') {
            throw new DomainException('Payout provider, method, and idempotency key are required.');
        }
        $row = $this->find($publicId);
        if (DB::table('exhibition_payout_attempts')->where('idempotency_key', $key)->exists()) {
            return $row;
        }
        $payout = DB::table('exhibition_payout_profiles as payouts')
            ->join('exhibition_verification_cases as cases', 'cases.id', '=', 'payouts.verification_case_id')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'cases.application_id')
            ->where('applications.core_user_id', $row->artist_user_id)
            ->select('payouts.*')
            ->first();
        if ($payout === null) {
            throw new DomainException('The artist must add a payout method before earnings can be transferred.');
        }
        if ($payout->verified_at === null) {
            throw new DomainException('The artist payout method must be financially verified before earnings can be transferred.');
        }

        return DB::transaction(function () use ($row, $actor, $provider, $method, $key): object {
            if (DB::table('exhibition_payout_attempts')->where('settlement_id', $row->id)->whereIn('state', ['scheduled', 'paid'])->exists()) {
                throw new DomainException('An active or completed payout already exists.');
            }
            DB::table('exhibition_payout_attempts')->insert(['public_id' => (string) Str::uuid(), 'settlement_id' => $row->id, 'idempotency_key' => $key, 'provider' => $provider, 'state' => 'scheduled', 'attempt_number' => DB::table('exhibition_payout_attempts')->where('settlement_id', $row->id)->count() + 1, 'created_at' => now(), 'updated_at' => now()]);

            return $this->transition($row->public_id, ['eligible_for_settlement', 'failed'], 'scheduled_for_payment', 'payout_scheduled', $key.':event', 'External payout instruction scheduled.', $actor, ['payout_method' => $method]);
        });
    }

    public function recordPaymentSent(string $publicId, int $actor, string $providerReference, string $reason, string $key): object
    {
        $providerReference = trim($providerReference);
        $reason = trim($reason);
        $key = trim($key);
        if ($providerReference === '' || $reason === '' || $key === '') {
            throw new DomainException('Payment reference, reason, and request key are required.');
        }

        $row = $this->find($publicId);
        $payout = $this->verifiedPayoutProfile($row);

        return DB::transaction(function () use ($row, $payout, $actor, $providerReference, $reason, $key): object {
            if ($existing = DB::table('exhibition_payout_attempts')->where('idempotency_key', $key)->first()) {
                return $this->findById($existing->settlement_id);
            }
            if (DB::table('exhibition_payout_attempts')->where('provider_reference', $providerReference)->exists()) {
                throw new DomainException('This payment reference is already used.');
            }

            DB::table('exhibition_payout_attempts')->insert([
                'public_id' => (string) Str::uuid(),
                'settlement_id' => $row->id,
                'idempotency_key' => $key,
                'provider' => $payout->method,
                'provider_reference' => $providerReference,
                'state' => 'sent',
                'attempt_number' => DB::table('exhibition_payout_attempts')->where('settlement_id', $row->id)->count() + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result = $this->transition(
                $row->public_id,
                ['approved', 'failed'],
                'payment_sent',
                'payout_sent',
                $key.':event',
                $reason,
                $actor,
                ['payout_method' => $payout->method, 'payout_reference' => $providerReference],
                ['masked_destination' => $payout->masked_identifier],
            );
            $this->notifications->dispatchCommitted(
                'payout_sent',
                [$result->artist_user_id],
                ['message' => 'The administration sent your payout. Confirm receipt or report a problem from your earnings page.', 'link' => '/exhibitions/dashboard/earnings/'.$result->public_id],
                'payout-sent:'.$key,
            );

            return $result;
        }, 3);
    }

    public function confirmReceipt(string $publicId, int $artistUserId, string $key): object
    {
        $row = $this->find($publicId);
        if ((int) $row->artist_user_id !== $artistUserId) {
            abort(404);
        }

        return DB::transaction(function () use ($row, $artistUserId, $key): object {
            $attempt = DB::table('exhibition_payout_attempts')
                ->where('settlement_id', $row->id)
                ->where('state', 'sent')
                ->orderByDesc('attempt_number')
                ->first() ?? throw new DomainException('No sent payment is waiting for confirmation.');
            DB::table('exhibition_payout_attempts')->where('id', $attempt->id)->update(['state' => 'paid', 'updated_at' => now()]);

            return $this->transition(
                $row->public_id,
                ['payment_sent'],
                'paid',
                'payout_received_confirmed',
                $key,
                'Artist confirmed receipt of the payout.',
                $artistUserId,
                ['paid_at' => now()],
            );
        }, 3);
    }

    public function payoutOutcome(string $publicId, string $attemptKey, string $providerReference, bool $paid, ?string $failureReason = null, ?int $actor = null): object
    {
        $attemptKey = trim($attemptKey);
        $providerReference = trim($providerReference);
        $failureReason = $failureReason === null ? null : trim($failureReason);
        if ($attemptKey === '' || $providerReference === '') {
            throw new DomainException('Payout attempt and provider reference are required.');
        }
        if (! $paid && ($failureReason === null || $failureReason === '')) {
            throw new DomainException('A failed payout outcome requires the provider failure reason.');
        }
        $row = $this->find($publicId);
        $attempt = DB::table('exhibition_payout_attempts')->where('settlement_id', $row->id)->where('idempotency_key', $attemptKey)->first() ?? throw new DomainException('Unknown payout attempt.');
        if (in_array($attempt->state, ['paid', 'failed'], true)) {
            return $row;
        }

        return DB::transaction(function () use ($row, $attempt, $providerReference, $paid, $failureReason, $actor): object {
            DB::table('exhibition_payout_attempts')->where('id', $attempt->id)->update(['provider_reference' => $providerReference, 'state' => $paid ? 'sent' : 'failed', 'failure_reason' => $paid ? null : trim((string) $failureReason), 'updated_at' => now()]);
            $result = $this->transition($row->public_id, ['scheduled_for_payment'], $paid ? 'payment_sent' : 'failed', $paid ? 'payout_sent' : 'payout_failed', 'payout-callback:'.$attempt->id, $paid ? 'Payment provider confirmed that the payout was sent; artist confirmation is pending.' : (trim((string) $failureReason) ?: 'External provider reported failure.'), $actor, $paid ? ['payout_reference' => $providerReference] : []);
            $this->notifications->dispatchCommitted($paid ? 'payout_sent' : 'payout_failed', [$result->artist_user_id], ['message' => $paid ? 'Your payout was sent. Confirm receipt or report a problem from your earnings page.' : 'Your payout requires platform follow-up.', 'link' => '/exhibitions/dashboard/earnings/'.$result->public_id], 'payout-outcome:'.$attempt->id);

            return $result;
        });
    }

    public function hold(string $publicId, string $reason, string $key, ?int $actor = null): object
    {
        if (trim($reason) === '') {
            throw new DomainException('Hold reason is required.');
        }$r = $this->find($publicId);

        return $this->transition($publicId, [$r->state], 'held', 'settlement_held', $key, $reason, $actor, ['hold_reason' => $reason], ['previous_state' => $r->state]);
    }

    public function release(string $publicId, string $reason, string $key, ?int $actor = null): object
    {
        $r = $this->find($publicId);

        $heldEvent = DB::table('exhibition_settlement_events')
            ->where('settlement_id', $r->id)
            ->where('action', 'settlement_held')
            ->orderByDesc('id')
            ->first();
        $metadata = $heldEvent?->metadata ? json_decode((string) $heldEvent->metadata, true) : [];
        $target = (string) ($metadata['previous_state'] ?? 'under_review');
        if (! in_array($target, ['return_window', 'payout_requested', 'approved', 'payment_sent', 'under_review'], true)) {
            $target = 'under_review';
        }

        return $this->transition($publicId, ['held'], $target, 'settlement_released', $key, $reason, $actor, ['hold_reason' => null]);
    }

    public function reverse(string $publicId, int $amountMinor, string $reason, string $key, int $actor): object
    {
        $r = $this->find($publicId);
        $amount = $this->minor($amountMinor);
        if ($amount === 0 || trim($reason) === '') {
            throw new DomainException('Reversal amount and reason are required.');
        }$this->ledger($r->id, 'reversal', 'credit', $amount, $r->currency, $key, $reason, $actor);

        return $this->transition($publicId, [$r->state], 'reversed', 'settlement_reversed', $key.':event', $reason, $actor);
    }

    public function cancelSale(string $publicId, string $reason, string $key, ?int $actor = null): object
    {
        $reason = trim($reason);
        $key = trim($key);
        if ($reason === '' || $key === '') {
            throw new DomainException('Sale cancellation requires a reason and idempotency key.');
        }

        return DB::transaction(function () use ($publicId, $reason, $key, $actor): object {
            $existing = DB::table('exhibition_settlement_events')->where('idempotency_key', $key.':event')->first();
            if ($existing) {
                return $this->findById($existing->settlement_id);
            }

            $row = DB::table('exhibition_settlements')->where('public_id', $publicId)->lockForUpdate()->first() ?? abort(404);
            if ($row->state === 'reversed') {
                return $row;
            }

            $reversedAmount = (int) $row->platform_commission_minor + (int) $row->artist_net_minor;
            $this->ledger($row->id, 'sale_reversal', 'debit', $reversedAmount, $row->currency, $key.':sale', $reason, $actor);
            if ((int) $row->platform_commission_minor > 0) {
                $this->ledger($row->id, 'platform_commission_reversal', 'credit', (int) $row->platform_commission_minor, $row->currency, $key.':commission', 'Platform Commission reversed after cancellation or return.', $actor);
            }
            if ((int) $row->artist_net_minor > 0) {
                $this->ledger($row->id, 'artist_earning_reversal', 'credit', (int) $row->artist_net_minor, $row->currency, $key.':artist', 'Artist earning reversed after cancellation or return.', $actor);
            }

            DB::table('exhibition_settlements')->where('id', $row->id)->update([
                'state' => 'reversed',
                'hold_reason' => $reason,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
            $this->event($row->id, $row->state, 'reversed', 'sale_cancelled', $key.':event', $reason, $actor, [
                'platform_commission_reversed_minor' => (int) $row->platform_commission_minor,
                'artist_earning_reversed_minor' => (int) $row->artist_net_minor,
            ]);

            return $this->findById($row->id);
        }, 3);
    }

    public function balance(int $settlementId): array
    {
        $q = DB::table('exhibition_settlement_ledger_entries')->where('settlement_id', $settlementId);

        return ['credits' => (int) (clone $q)->where('direction', 'credit')->sum('amount_minor'), 'debits' => (int) (clone $q)->where('direction', 'debit')->sum('amount_minor')];
    }

    private function assertGates(object $row, bool $approval): void
    {
        if (! $row->payment_collected_at) {
            throw new DomainException('Collected order value is required.');
        }

        $f = DB::table('exhibition_fulfillments')->where('id', $row->fulfillment_id)->first();
        if (! $f?->delivered_at || $f->state !== 'delivered') {
            throw new DomainException('Delivered fulfillment is required.');
        }

        if (! $row->return_window_ends_at || now()->lt($row->return_window_ends_at)) {
            throw new DomainException('Return window has not expired.');
        }

        if (Schema::hasTable('exhibition_dispute_cases') && DB::table('exhibition_dispute_cases')->where('settlement_id', $row->id)->whereNotIn('state', ['resolved', 'closed'])->exists()) {
            throw new DomainException('Open dispute blocks settlement.');
        }

        if ($approval && ! $row->finance_approved_at) {
            throw new DomainException('Finance approval is required.');
        }
    }

    private function verifiedPayoutProfile(object $row): object
    {
        $payout = DB::table('exhibition_payout_profiles as payouts')
            ->join('exhibition_verification_cases as cases', 'cases.id', '=', 'payouts.verification_case_id')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'cases.application_id')
            ->where('applications.core_user_id', $row->artist_user_id)
            ->select('payouts.*')
            ->first();
        if ($payout === null) {
            throw new DomainException('Add a payout method before requesting your earnings.');
        }
        if ($payout->verified_at === null) {
            throw new DomainException('Your payout method must be verified before requesting your earnings.');
        }

        return $payout;
    }

    private function transition(string $id, array $allowed, string $to, string $action, string $key, string $reason, ?int $actor = null, array $updates = [], array $metadata = []): object
    {
        return DB::transaction(function () use ($id, $allowed, $to, $action, $key, $reason, $actor, $updates, $metadata): object {
            $existing = DB::table('exhibition_settlement_events')->where('idempotency_key', $key)->first();
            if ($existing) {
                return $this->findById($existing->settlement_id);
            }$r = DB::table('exhibition_settlements')->where('public_id', $id)->lockForUpdate()->first() ?? abort(404);
            if (! in_array($r->state, $allowed, true)) {
                throw new DomainException('Invalid or stale settlement transition.');
            }DB::table('exhibition_settlements')->where('id', $r->id)->update($updates + ['state' => $to, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()]);
            $this->event($r->id, $r->state, $to, $action, $key, $reason, $actor, $metadata);

            return $this->findById($r->id);
        }, 3);
    }

    private function ledger(int $id, string $type, string $direction, int $amount, string $currency, string $key, string $description, ?int $actor = null): void
    {
        DB::table('exhibition_settlement_ledger_entries')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'settlement_id' => $id, 'entry_type' => $type, 'direction' => $direction, 'amount_minor' => $amount, 'currency' => $currency, 'idempotency_key' => $key, 'description' => $description, 'actor_user_id' => $actor, 'occurred_at' => now(), 'created_at' => now()]);
    }

    private function event(int $id, ?string $from, string $to, string $action, string $key, string $reason, ?int $actor = null, array $metadata = []): void
    {
        DB::table('exhibition_settlement_events')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'settlement_id' => $id, 'actor_user_id' => $actor, 'action' => $action, 'from_state' => $from, 'to_state' => $to, 'idempotency_key' => $key, 'reason' => $reason, 'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function minor(mixed $value, bool $signed = false): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value))) {
            throw new DomainException('Money values must be integer minor units.');
        }$n = (int) $value;
        if (! $signed && $n < 0) {
            throw new DomainException('Money values cannot be negative.');
        }

        return $n;
    }

    private function find(string $id): object
    {
        return DB::table('exhibition_settlements')->where('public_id', $id)->first() ?? abort(404);
    }

    private function findById(int $id): object
    {
        return DB::table('exhibition_settlements')->where('id', $id)->first() ?? abort(404);
    }
}
