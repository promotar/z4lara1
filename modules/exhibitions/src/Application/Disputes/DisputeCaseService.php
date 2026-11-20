<?php

namespace Modules\Exhibition\Application\Disputes;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Finance\ArtistSettlementService;
use Modules\Exhibition\Application\Fulfillment\ArtworkFulfillmentService;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;
use Modules\Exhibition\Application\Security\PrivateEvidenceUpload;

final readonly class DisputeCaseService
{
    private const TYPES = ['shipping_delay', 'refusal_of_delivery', 'return_request', 'shipping_damage', 'artwork_differs_from_description', 'loss', 'ownership_authenticity_dispute', 'payout_not_received', 'other'];

    public function __construct(private ArtistSettlementService $settlements, private ArtworkFulfillmentService $fulfillment, private ArtworkInventoryService $inventory, private ExhibitionNotificationService $notifications) {}

    public function create(string $source, string $sourceRef, string $fulfillmentPublicId, string $type, string $priority, string $subject, string $summary, string $key, ?int $customer = null, ?int $actor = null): object
    {
        if (! in_array($source, ['customer', 'store', 'artist', 'staff'], true) || ! in_array($type, self::TYPES, true) || ! in_array($priority, ['low', 'normal', 'high', 'critical'], true)) {
            throw new DomainException('Unsupported dispute source, type, or priority.');
        }if (trim($subject) === '' || trim($summary) === '') {
            throw new DomainException('Case subject and summary are required.');
        }
        if ($e = DB::table('exhibition_dispute_events')->where('idempotency_key', $key)->first()) {
            return $this->findById($e->case_id);
        }$f = DB::table('exhibition_fulfillments')->where('public_id', $fulfillmentPublicId)->first() ?? abort(404);
        $settlement = DB::table('exhibition_settlements')->where('fulfillment_id', $f->id)->first();

        return DB::transaction(function () use ($source, $sourceRef, $type, $priority, $subject, $summary, $key, $customer, $actor, $f, $settlement): object {
            $id = DB::table('exhibition_dispute_cases')->insertGetId(['public_id' => (string) Str::uuid(), 'source' => $source, 'source_reference' => $sourceRef, 'type' => $type, 'priority' => $priority, 'state' => 'triage', 'fulfillment_id' => $f->id, 'settlement_id' => $settlement?->id, 'artwork_id' => $f->artwork_id, 'artist_user_id' => $f->artist_user_id, 'customer_user_id' => $customer, 'customer_reference' => $source === 'customer' ? $sourceRef : null, 'response_due_at' => now()->addHours($priority === 'critical' ? 1 : 8), 'resolution_due_at' => now()->addDays($priority === 'critical' ? 1 : 5), 'subject' => trim($subject), 'summary' => trim($summary), 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->event($id, null, 'triage', 'case_created', $key, 'Case received through platform-controlled interface.', $actor, $source);
            if ($settlement && $this->requiresHold($type)) {
                $this->settlements->hold($settlement->public_id, 'Dispute '.$sourceRef.': '.$type, 'dispute-hold:'.$key, $actor);
            }$case = $this->findById($id);
            $this->notifications->dispatchCommitted('dispute_opened_or_changed', array_values(array_filter([$f->artist_user_id, $customer])), ['message' => 'A platform-mediated case was opened or changed.', 'title' => $subject, 'link' => '/exhibitions/dashboard/cases/'.$case->public_id], 'dispute:'.$key);

            return $case;
        }, 3);
    }

    public function message(string $casePublicId, int $sender, string $role, string $audience, string $body, string $key): object
    {
        $c = $this->find($casePublicId);
        if (in_array($c->state, ['resolved', 'closed'], true)) {
            throw new DomainException('Closed case history cannot be changed.');
        }$allowed = match ($role) {
            'customer' => ['support'],'artist' => ['support'],'support','reviewer','operations','finance' => ['internal', 'customer', 'artist', 'all'],default => []
        };
        if (! in_array($audience, $allowed, true)) {
            throw new DomainException('Message audience is not allowed for this role.');
        }if (trim($body) === '') {
            throw new DomainException('Message is required.');
        }DB::table('exhibition_dispute_messages')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'case_id' => $c->id, 'sender_user_id' => $sender, 'sender_role' => $role, 'audience' => $audience, 'body' => trim($body), 'idempotency_key' => $key, 'created_at' => now()]);
        $this->event($c->id, $c->state, $c->state, 'message_added', 'message-event:'.$key, 'Auditable mediated case message added.', $sender, $role, ['audience' => $audience]);

        return $this->find($casePublicId);
    }

    public function evidence(string $casePublicId, int $user, string $role, string $audience, string $evidenceRole, UploadedFile $file): object
    {
        $c = $this->find($casePublicId);
        if (in_array($c->state, ['resolved', 'closed'], true)) {
            throw new DomainException('Closed case evidence cannot be changed.');
        }if (! in_array($audience, ['internal', 'customer', 'artist', 'all'], true)) {
            throw new DomainException('Invalid evidence audience.');
        }$upload = PrivateEvidenceUpload::inspect($file, (int) config('exhibitions.disputes.maximum_kilobytes', 20480) * 1024);
        $public = (string) Str::uuid();
        $path = 'exhibitions/disputes/'.$c->public_id.'/'.$public.'.'.$upload['extension'];
        if (! Storage::disk('local')->put($path, $upload['bytes'])) {
            throw new DomainException('Evidence could not be stored securely.');
        }DB::table('exhibition_dispute_evidence')->insert(['public_id' => $public, 'case_id' => $c->id, 'uploaded_by' => $user, 'uploader_role' => $role, 'audience' => $audience, 'role' => $evidenceRole, 'disk' => 'local', 'path' => $path, 'original_name' => $upload['original_name'], 'mime_type' => $upload['mime_type'], 'size_bytes' => $upload['size_bytes'], 'checksum_sha256' => $upload['checksum_sha256'], 'created_at' => now()]);

        return DB::table('exhibition_dispute_evidence')->where('public_id', $public)->first();
    }

    public function assign(string $id, int $actor, int $owner, string $reason, string $key): object
    {
        return $this->transition($id, ['triage', 'under_review', 'awaiting_customer', 'awaiting_artist'], 'under_review', 'assigned', $reason, $key, $actor, 'support', ['assigned_to' => $owner]);
    }

    public function requestInformation(string $id, int $actor, string $party, string $request, string $key): object
    {
        $target = match ($party) {
            'customer' => 'awaiting_customer','artist' => 'awaiting_artist',default => throw new DomainException('Information may be requested only from customer or artist.')
        };
        $this->message($id, $actor, 'support', $party, $request, $key.':message');
        $c = $this->find($id);

        return $this->transition($id, [$c->state], $target, 'information_requested', $request, $key, $actor, 'support');
    }

    public function decide(string $id, int $actor, string $decision, string $reason, array $impacts, string $key): object
    {
        if (trim($decision) === '' || trim($reason) === '') {
            throw new DomainException('Final decision and reason are required.');
        }$c = $this->find($id);
        if (in_array($c->state, ['resolved', 'closed'], true)) {
            throw new DomainException('Closed-case history cannot be overwritten.');
        }$result = $this->transition($id, ['triage', 'under_review', 'awaiting_customer', 'awaiting_artist'], 'resolved', 'final_decision', $reason, $key, $actor, 'support', ['final_decision' => $decision, 'decision_reason' => $reason, 'financial_impact' => json_encode($impacts['financial'] ?? [], JSON_THROW_ON_ERROR), 'inventory_impact' => json_encode($impacts['inventory'] ?? [], JSON_THROW_ON_ERROR), 'fulfillment_impact' => json_encode($impacts['fulfillment'] ?? [], JSON_THROW_ON_ERROR), 'decided_by' => $actor, 'decided_at' => now()], $impacts);
        $this->syncDecision($result, $actor, $decision, $reason, $key);
        if ($result->settlement_id) {
            $s = DB::table('exhibition_settlements')->where('id', $result->settlement_id)->first();
            if ($s?->state === 'held') {
                $this->settlements->release($s->public_id, 'Dispute resolved: '.$reason, 'dispute-release:'.$key, $actor);
            }
        }

        return $this->find($id);
    }

    public function close(string $id, int $actor, string $reason, string $key): object
    {
        return $this->transition($id, ['resolved'], 'closed', 'case_closed', $reason, $key, $actor, 'support');
    }

    public function reopen(string $id, int $actor, string $reason, string $key): object
    {
        $c = $this->find($id);
        $r = $this->transition($id, ['resolved', 'closed'], 'under_review', 'case_reopened', $reason, $key, $actor, 'support', ['reopen_count' => DB::raw('reopen_count + 1'), 'final_decision' => null, 'decision_reason' => null, 'decided_by' => null, 'decided_at' => null]);
        if ($r->settlement_id) {
            $s = DB::table('exhibition_settlements')->where('id', $r->settlement_id)->first();
            if ($s && $s->state !== 'held') {
                $this->settlements->hold($s->public_id, 'Reopened dispute '.$r->public_id, 'dispute-reopen-hold:'.$key, $actor);
            }
        }

        return $r;
    }

    public function find(string $id): object
    {
        return DB::table('exhibition_dispute_cases')->where('public_id', $id)->first() ?? abort(404);
    }

    private function findById(int $id): object
    {
        return DB::table('exhibition_dispute_cases')->where('id', $id)->first() ?? abort(404);
    }

    private function syncDecision(object $c, int $actor, string $decision, string $reason, string $key): void
    {
        $f = DB::table('exhibition_fulfillments')->where('id', $c->fulfillment_id)->first();
        $item = DB::table('exhibition_artwork_inventory_items')->where('id', $f->inventory_item_id)->first();
        if ($decision === 'return_approved' && $f->state === 'delivered') {
            $this->fulfillment->platformTransition($actor, $f->public_id, 'return_requested', $reason, 'dispute-fulfillment:'.$key);
        }if (in_array($decision, ['damage_confirmed', 'loss_confirmed'], true)) {
            $target = $decision === 'damage_confirmed' ? 'damaged' : 'lost';
            $allowed = ['ready_for_pickup', 'collected', 'quality_inspection', 'ready_for_shipment', 'shipped', 'delayed'];
            if (in_array($f->state, $allowed, true)) {
                $this->fulfillment->platformTransition($actor, $f->public_id, $target, $reason, 'dispute-fulfillment:'.$key);
            }if ($item->state !== 'unavailable') {
                $this->inventory->override($actor, $item->public_id, 'unavailable', $reason, 'dispute-inventory:'.$key);
            }
        }if ($decision === 'ownership_claim_upheld') {
            DB::table('exhibition_artwork_publications')->where('artwork_id', $c->artwork_id)->update(['state' => 'withdrawn', 'failure_code' => 'ownership_dispute', 'failure_message' => 'Publication withdrawn by authorized ownership dispute decision.', 'updated_at' => now()]);
        }
    }

    private function transition(string $id, array $allowed, string $to, string $action, string $reason, string $key, int $actor, string $role, array $updates = [], array $metadata = []): object
    {
        if (trim($reason) === '') {
            throw new DomainException('Auditable reason is required.');
        }

        return DB::transaction(function () use ($id, $allowed, $to, $action, $reason, $key, $actor, $role, $updates, $metadata): object {
            $e = DB::table('exhibition_dispute_events')->where('idempotency_key', $key)->first();
            if ($e) {
                return $this->findById($e->case_id);
            }$c = DB::table('exhibition_dispute_cases')->where('public_id', $id)->lockForUpdate()->first() ?? abort(404);
            if (! in_array($c->state, $allowed, true)) {
                throw new DomainException('Invalid or stale case transition.');
            }DB::table('exhibition_dispute_cases')->where('id', $c->id)->update($updates + ['state' => $to, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now()]);
            $this->event($c->id, $c->state, $to, $action, $key, $reason, $actor, $role, $metadata);

            return $this->findById($c->id);
        }, 3);
    }

    private function event(int $id, ?string $from, string $to, string $action, string $key, string $reason, ?int $actor, string $role, array $metadata = []): void
    {
        DB::table('exhibition_dispute_events')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'case_id' => $id, 'actor_user_id' => $actor, 'actor_role' => $role, 'action' => $action, 'from_state' => $from, 'to_state' => $to, 'idempotency_key' => $key, 'reason' => $reason, 'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function requiresHold(string $type): bool
    {
        return ! in_array($type, ['shipping_delay', 'other'], true);
    }
}
