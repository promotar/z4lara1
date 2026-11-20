<?php

namespace Modules\Exhibition\Application\Review;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Modules\Exhibition\Application\Authenticity\ArtworkAuthenticityService;
use Modules\Exhibition\Application\Pricing\ArtworkPricingService;
use Modules\Exhibition\Application\Publication\ArtworkPublicationService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Throwable;

final readonly class ArtworkReviewService
{
    private const SECTIONS = ['content', 'images', 'authenticity', 'pricing'];

    private const DECISIONS = ['approved', 'approved_with_adjustments', 'changes_requested', 'documents_requested', 'rejected', 'escalated'];

    private const OVERALL_DECISIONS = ['approved', 'changes_requested', 'rejected'];

    private const TARGETS = ['field', 'media', 'evidence', 'general'];

    private const FIELDS = ['title', 'short_description', 'detailed_description', 'category', 'width', 'height', 'depth', 'material', 'technique', 'completion_year', 'condition', 'edition_type'];

    public function __construct(
        private ArtworkService $artworks,
        private ArtworkAuthenticityService $authenticity,
        private ArtworkPricingService $pricing,
        private ArtworkPublicationService $publication,
        private AuditRecorder $audit,
    ) {}

    public function sections(): array
    {
        return self::SECTIONS;
    }

    public function decisions(): array
    {
        return self::DECISIONS;
    }

    public function overallDecisions(): array
    {
        return self::OVERALL_DECISIONS;
    }

    public function openRound(int $reviewer, string $public, ?int $assignee = null): object
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        $active = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->whereIn('state', ['under_review', 'changes_requested', 'documents_requested', 'escalated'])->orderByDesc('round_number')->first();
        if ($active && $active->state === 'under_review') {
            return $active;
        }$number = ((int) DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->max('round_number')) + 1;
        $id = DB::table('exhibition_artwork_review_rounds')->insertGetId(['public_id' => (string) Str::uuid(), 'artwork_id' => $art->id, 'round_number' => $number, 'state' => 'under_review', 'opened_by' => $reviewer, 'assigned_to' => $assignee ?? $reviewer, 'lock_version' => 1, 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach (self::SECTIONS as $section) {
            DB::table('exhibition_artwork_review_sections')->insert(['public_id' => (string) Str::uuid(), 'round_id' => $id, 'section' => $section, 'state' => 'pending', 'assigned_to' => $assignee ?? $reviewer, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }$round = DB::table('exhibition_artwork_review_rounds')->where('id', $id)->first();
        $this->event($round, $reviewer, 'round_opened', ['round' => $number]);

        return $round;
    }

    /**
     * Approve a submitted artwork without manual review. Only used for artists
     * that an administrator explicitly granted automatic approval.
     */
    public function approveAutomatically(int $actor, string $public, string $reason = 'Automatic approval granted to this artist by the platform.'): void
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        if ($art->state !== 'submitted') {
            return;
        }

        $this->pricing->syncArtworkRequestedPrice($public);
        $round = $this->openRound($actor, $public);
        $now = now();

        foreach (self::SECTIONS as $section) {
            DB::table('exhibition_artwork_review_sections')
                ->where('round_id', $round->id)
                ->where('section', $section)
                ->update([
                    'state' => 'approved', 'decided_by' => $actor, 'reason' => $reason,
                    'opened_at' => $now, 'decided_at' => $now,
                    'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now,
                ]);
            $this->event($round, $actor, 'section_decided', ['section' => $section, 'decision' => 'approved', 'automatic' => true]);
        }

        $fresh = DB::table('exhibition_artwork_review_rounds')->where('id', $round->id)->first();
        $this->decideOverall($actor, $public, (string) $round->public_id, 'approved', $reason, (int) $fresh->lock_version);
    }

    public function reviewerWorkspace(string $public): array
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);

        return $this->workspace($art);
    }

    public function ownerWorkspace(int $user, string $public): array
    {
        $art = $this->artworks->owned($user, $public);

        return $this->workspace($art);
    }

    private function workspace(object $art): array
    {
        $rounds = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->orderByDesc('round_number')->get();
        $current = $rounds->first();

        return ['artwork' => $art, 'rounds' => $rounds, 'currentRound' => $current, 'sections' => $current ? DB::table('exhibition_artwork_review_sections')->where('round_id', $current->id)->orderByRaw("CASE section WHEN 'content' THEN 1 WHEN 'images' THEN 2 WHEN 'authenticity' THEN 3 ELSE 4 END")->get() : collect(), 'comments' => $current ? DB::table('exhibition_artwork_review_comments')->where('round_id', $current->id)->orderBy('created_at')->get() : collect(), 'events' => $current ? DB::table('exhibition_artwork_review_events')->where('round_id', $current->id)->orderBy('occurred_at')->get() : collect(), 'media' => DB::table('exhibition_artwork_media')->where('artwork_id', $art->id)->where('state', 'ready')->orderBy('sort_order')->get(), 'authenticity' => DB::table('exhibition_artwork_authenticity')->where('artwork_id', $art->id)->first(), 'authEvidence' => DB::table('exhibition_artwork_evidence')->where('artwork_id', $art->id)->where('state', 'ready')->get(), 'pricingEvidence' => DB::table('exhibition_artwork_pricing_evidence')->where('artwork_id', $art->id)->get()];
    }

    public function decide(int $reviewer, string $public, string $roundPublic, string $section, string $decision, string $reason, ?string $adjustment, int $expectedVersion): void
    {
        if (! in_array($section, self::SECTIONS, true) || ! in_array($decision, self::DECISIONS, true)) {
            throw new DomainException('Unsupported review section or decision.');
        }if (trim($reason) === '') {
            throw new DomainException('Every review decision requires a reason.');
        }if ($decision === 'approved_with_adjustments' && trim((string) $adjustment) === '') {
            throw new DomainException('Approved adjustments must be visible and described.');
        }$art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        $round = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->where('public_id', $roundPublic)->first() ?? abort(404);
        if ($round->state !== 'under_review') {
            throw new DomainException('Only an active review round can be decided.');
        }$row = DB::table('exhibition_artwork_review_sections')->where('round_id', $round->id)->where('section', $section)->first() ?? abort(404);
        if ((int) $row->lock_version !== $expectedVersion) {
            throw new DomainException('This review section changed. Reload before deciding.');
        }if (in_array($decision, ['approved', 'approved_with_adjustments'], true)) {
            $this->assertSectionReady($art, $section);
        }DB::table('exhibition_artwork_review_sections')->where('id', $row->id)->where('lock_version', $expectedVersion)->update(['state' => $decision, 'decided_by' => $reviewer, 'reason' => trim($reason), 'adjustment_summary' => trim((string) $adjustment) ?: null, 'opened_at' => $row->opened_at ?? now(), 'decided_at' => now(), 'lock_version' => $expectedVersion + 1, 'updated_at' => now()]);
        $this->event($round, $reviewer, 'section_decided', ['section' => $section, 'decision' => $decision, 'version' => $expectedVersion + 1]);
        $overall = $this->derive($round->id);
        $this->applyOverall($art, $round, $overall, $reviewer, $reason);
        $this->audit->record(new AuditEntryData(action: 'exhibitions.artwork.review_section_decided', targetType: 'artwork', targetId: $public, previousValues: ['section' => $section, 'state' => $row->state, 'version' => $expectedVersion], newValues: ['section' => $section, 'state' => $decision, 'overall' => $overall, 'version' => $expectedVersion + 1], reason: $reason, actorUserId: $reviewer));
        if ($overall === 'approved') {
            $this->publishApprovedArtwork($reviewer, $art, $round, $reason);
        }
    }

    public function decideOverall(int $reviewer, string $public, string $roundPublic, string $decision, ?string $reason, int $expectedVersion): void
    {
        if (! in_array($decision, self::OVERALL_DECISIONS, true)) {
            throw new DomainException('Choose approval, rejection, or request changes for the complete artwork.');
        }

        $reason = trim((string) $reason);
        if (in_array($decision, ['changes_requested', 'rejected'], true) && $reason === '') {
            throw new DomainException('A clear reason is required when rejecting an artwork or requesting changes.');
        }
        if ($reason === '') {
            $reason = 'Complete artwork approved after manual review.';
        }

        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        $round = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->where('public_id', $roundPublic)->first() ?? abort(404);
        if ($round->state !== 'under_review') {
            throw new DomainException('Only an active review round can be decided.');
        }
        if ((int) $round->lock_version !== $expectedVersion) {
            throw new DomainException('This artwork review changed. Reload before recording a decision.');
        }
        if ($decision === 'approved') {
            $this->pricing->syncArtworkRequestedPrice($public);
            foreach (['content', 'images'] as $section) {
                $this->assertSectionReady($art, $section);
            }
            $this->assertAuthenticityReviewable($art);
            $this->assertPricingReviewable($art);
        }

        DB::transaction(function () use ($reviewer, $public, $art, $round, $decision, $reason, $expectedVersion): void {
            $updated = DB::table('exhibition_artwork_review_rounds')
                ->where('id', $round->id)
                ->where('lock_version', $expectedVersion)
                ->where('state', 'under_review')
                ->update([
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
            if ($updated !== 1) {
                throw new DomainException('This artwork review changed. Reload before recording a decision.');
            }

            if ($decision === 'approved') {
                $declaration = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $art->id)->first();
                if ($declaration?->state !== 'documentation_accepted') {
                    $references = DB::table('exhibition_artwork_evidence')
                        ->where('artwork_id', $art->id)
                        ->where('state', 'ready')
                        ->pluck('public_id')
                        ->all();
                    $this->authenticity->review(
                        $reviewer,
                        $public,
                        'documentation_accepted',
                        $reason,
                        'Accepted as part of the complete artwork review.',
                        null,
                        $references,
                    );
                }

                $pricing = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
                if ($pricing?->state !== 'approved') {
                    $references = DB::table('exhibition_artwork_pricing_evidence')
                        ->where('artwork_id', $art->id)
                        ->pluck('public_id')
                        ->all();
                    $this->pricing->review(
                        $reviewer,
                        $public,
                        'approved',
                        null,
                        $reason,
                        $references,
                    );
                }
            }

            DB::table('exhibition_artwork_review_sections')->where('round_id', $round->id)->update([
                'state' => $decision,
                'decided_by' => $reviewer,
                'reason' => $reason,
                'adjustment_summary' => null,
                'opened_at' => now(),
                'decided_at' => now(),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

            $this->applyOverall($art, $round, $decision, $reviewer, $reason);
            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.artwork.review_decided',
                targetType: 'artwork',
                targetId: $public,
                previousValues: ['state' => $round->state, 'version' => $expectedVersion],
                newValues: ['state' => $decision, 'version' => $expectedVersion + 1],
                reason: $reason,
                actorUserId: $reviewer,
            ));
        });

        if ($decision === 'approved') {
            $this->publishApprovedArtwork($reviewer, $art, $round, $reason);
        }
    }

    public function comment(int $reviewer, string $public, string $roundPublic, string $section, string $targetType, string $targetReference, string $body): object
    {
        if (! in_array($targetType, self::TARGETS, true) || trim($body) === '') {
            throw new DomainException('A valid target and actionable comment are required.');
        }$art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        $round = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->where('public_id', $roundPublic)->first() ?? abort(404);
        $sectionRow = DB::table('exhibition_artwork_review_sections')->where('round_id', $round->id)->where('section', $section)->first() ?? abort(404);
        $this->assertTarget($art, $targetType, $targetReference);
        $id = DB::table('exhibition_artwork_review_comments')->insertGetId(['public_id' => (string) Str::uuid(), 'round_id' => $round->id, 'section_id' => $sectionRow->id, 'author_user_id' => $reviewer, 'author_role' => 'reviewer', 'target_type' => $targetType, 'target_reference' => $targetReference, 'body' => trim($body), 'state' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $comment = DB::table('exhibition_artwork_review_comments')->where('id', $id)->first();
        $this->event($round, $reviewer, 'comment_added', ['comment' => $comment->public_id, 'target' => $targetType.':'.$targetReference]);

        return $comment;
    }

    public function reply(int $artist, string $public, string $commentPublic, string $body): void
    {
        $art = $this->artworks->owned($artist, $public);
        if (trim($body) === '') {
            throw new DomainException('A response is required.');
        }$comment = DB::table('exhibition_artwork_review_comments as c')->join('exhibition_artwork_review_rounds as r', 'r.id', '=', 'c.round_id')->where('r.artwork_id', $art->id)->where('c.public_id', $commentPublic)->select('c.*')->first() ?? abort(404);
        if ($comment->author_role !== 'reviewer' || $comment->state === 'resolved') {
            throw new DomainException('Only an open reviewer comment can be answered.');
        }DB::transaction(function () use ($comment, $artist, $body) {
            DB::table('exhibition_artwork_review_comments')->insert(['public_id' => (string) Str::uuid(), 'round_id' => $comment->round_id, 'section_id' => $comment->section_id, 'parent_comment_id' => $comment->id, 'author_user_id' => $artist, 'author_role' => 'artist', 'target_type' => $comment->target_type, 'target_reference' => $comment->target_reference, 'body' => trim($body), 'state' => 'answered', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('exhibition_artwork_review_comments')->where('id', $comment->id)->update(['state' => 'answered', 'updated_at' => now()]);
        });
        $round = DB::table('exhibition_artwork_review_rounds')->where('id', $comment->round_id)->first();
        $this->event($round, $artist, 'comment_answered', ['comment' => $commentPublic]);
    }

    public function resubmit(int $artist, string $public, string $reason): object
    {
        $art = $this->artworks->owned($artist, $public);
        if (trim($reason) === '') {
            throw new DomainException('Resubmission requires a summary of revisions.');
        }$old = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->orderByDesc('round_number')->first();
        if (! $old || ! in_array($old->state, ['changes_requested', 'documents_requested', 'rejected'], true)) {
            throw new DomainException('This artwork is not awaiting resubmission.');
        }DB::table('exhibition_artwork_review_rounds')->where('id', $old->id)->update(['state' => 'resubmitted', 'resubmitted_at' => now(), 'updated_at' => now()]);
        $new = $this->openRound($artist, $public, $old->assigned_to);
        $this->event($new, $artist, 'artist_resubmitted', ['previous_round' => $old->round_number, 'summary' => $reason]);

        return $new;
    }

    public function derive(int $roundId): string
    {
        $states = DB::table('exhibition_artwork_review_sections')->where('round_id', $roundId)->pluck('state')->all();
        if (count($states) !== 4) {
            return 'under_review';
        }if (in_array('rejected', $states, true)) {
            return 'rejected';
        }if (in_array('escalated', $states, true)) {
            return 'escalated';
        }if (in_array('changes_requested', $states, true)) {
            return 'changes_requested';
        }if (in_array('documents_requested', $states, true)) {
            return 'documents_requested';
        }if (count(array_filter($states, fn ($s) => in_array($s, ['approved', 'approved_with_adjustments'], true))) === 4) {
            return 'approved';
        }

        return 'under_review';
    }

    private function assertSectionReady(object $art, string $section): void
    {
        if ($section === 'content') {
            foreach (['title', 'category'] as $f) {
                if ($art->{$f} === null || $art->{$f} === '') {
                    throw new DomainException('Content approval is blocked by incomplete required artwork fields.');
                }
            }
        }if ($section === 'images' && ! DB::table('exhibition_artwork_media')->where('artwork_id', $art->id)->where('state', 'ready')->exists()) {
            throw new DomainException('Image approval requires at least one ready artwork image.');
        }if ($section === 'authenticity' && ! DB::table('exhibition_artwork_authenticity')->where('artwork_id', $art->id)->where('state', 'documentation_accepted')->exists()) {
            throw new DomainException('Authenticity approval requires accepted documentation.');
        }if ($section === 'pricing' && ! DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->where('state', 'approved')->whereNotNull('approved_amount_snapshot')->exists()) {
            throw new DomainException('Pricing approval requires a mutually approved price snapshot.');
        }
    }

    private function assertAuthenticityReviewable(object $art): void
    {
        $declaration = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $art->id)->first();
        if (! $declaration) {
            throw new DomainException('Artwork approval requires the artist ownership declaration.');
        }
        if (! in_array($declaration->state, ['submitted', 'additional_evidence_required', 'documentation_accepted'], true)) {
            throw new DomainException('Artwork approval is blocked while the ownership documentation is disputed, rejected, or under investigation.');
        }
        if (! (bool) $declaration->may_display_and_sell || $declaration->declared_at === null) {
            throw new DomainException('Artwork approval requires a completed right-to-sell declaration.');
        }
        foreach (['inspiration_disclosure', 'digital_tools_disclosure', 'current_physical_location'] as $field) {
            if (trim((string) $declaration->{$field}) === '') {
                throw new DomainException('Artwork approval requires complete ownership and authenticity disclosures.');
            }
        }
    }

    private function assertPricingReviewable(object $art): void
    {
        $pricing = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
        if (! $pricing || $pricing->requested_amount === null) {
            throw new DomainException('Artwork approval requires a price submitted by the artist.');
        }
        if ($pricing->state === 'adjustment_suggested') {
            throw new DomainException('The artist must accept the proposed price before the artwork can be approved.');
        }
        if (! in_array($pricing->state, ['price_submitted', 'under_review', 'artist_accepted', 'approved'], true)) {
            throw new DomainException('Artwork approval is blocked until the pricing request is ready for a final decision.');
        }
        if ($pricing->state === 'artist_accepted' && $pricing->agreed_amount === null) {
            throw new DomainException('The accepted proposed price is incomplete and cannot be approved.');
        }
    }

    private function applyOverall(object $art, object $round, string $state, int $actor, string $reason): void
    {
        $updates = ['state' => $state, 'lock_version' => $round->lock_version + 1, 'updated_at' => now()];
        if (in_array($state, ['approved', 'rejected'], true)) {
            $updates['resolved_at'] = now();
        }DB::table('exhibition_artwork_review_rounds')->where('id', $round->id)->update($updates);
        $artState = ['approved' => 'approved', 'rejected' => 'rejected', 'changes_requested' => 'changes_required', 'documents_requested' => 'changes_required', 'escalated' => 'under_review'][$state] ?? 'under_review';
        $artUpdates = ['state' => $artState, 'updated_at' => now()];
        if ($state === 'approved') {
            $artUpdates += [
                'approved_at' => $art->approved_at ?? now(),
                'approved_by' => $actor,
                'approval_review_round_id' => $round->id,
            ];
        }
        DB::table('exhibition_artworks')->where('id', $art->id)->update($artUpdates);
        if ($state === 'approved') {
            $this->recordApprovalEvidence($art, $round, $actor, $reason);
        }
        if ($state !== 'under_review') {
            $this->event($round, $actor, 'overall_'.$state, ['reason' => $reason]);
        }
    }

    private function publishApprovedArtwork(int $actor, object $artwork, object $round, string $approvalReason): void
    {
        try {
            $this->publication->publish(
                $actor,
                (string) $artwork->public_id,
                'Automatic Store synchronization after final artwork approval: '.$approvalReason,
                'approval:'.$artwork->public_id.':'.$round->public_id,
            );
        } catch (Throwable $exception) {
            Log::error('Automatic Store synchronization failed after artwork approval.', [
                'artwork_public_id' => (string) $artwork->public_id,
                'review_round_public_id' => (string) $round->public_id,
                'exception_class' => $exception::class,
            ]);
        }
    }

    public function repairApprovalAudit(int $actor, string $public, string $reason): void
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        $round = DB::table('exhibition_artwork_review_rounds')->where('artwork_id', $art->id)->where('state', 'approved')->orderByDesc('round_number')->first();
        if ($art->state !== 'approved' || $round === null) {
            throw new DomainException('Only an approved artwork with an approved review round can be repaired.');
        }

        DB::table('exhibition_artworks')->where('id', $art->id)->update([
            'approved_at' => $art->approved_at ?? $round->resolved_at ?? now(),
            'approved_by' => $art->approved_by ?? $round->assigned_to ?? $round->opened_by ?? $actor,
            'approval_review_round_id' => $round->id,
            'updated_at' => now(),
        ]);
        $this->recordApprovalEvidence($art, $round, (int) ($art->approved_by ?? $round->assigned_to ?? $actor), $reason);
    }

    private function recordApprovalEvidence(object $art, object $round, int $actor, string $reason): void
    {
        $eventKey = hash('sha256', 'artwork-approved|'.$art->public_id.'|'.$round->public_id);
        DB::table('exhibition_artwork_state_history')->insertOrIgnore([
            'public_id' => (string) Str::uuid(),
            'artwork_id' => $art->id,
            'actor_user_id' => $actor,
            'from_state' => 'under_review',
            'to_state' => 'approved',
            'reason' => $reason,
            'review_round_id' => $round->id,
            'event_key' => $eventKey,
            'occurred_at' => $round->resolved_at ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.artwork.approved',
            targetType: 'artwork',
            targetId: $art->public_id,
            previousValues: ['state' => 'under_review'],
            newValues: ['state' => 'approved', 'review_round' => $round->public_id],
            reason: $reason,
            actorUserId: $actor,
            idempotencyKey: 'artwork-approved:'.$art->public_id.':'.$round->public_id,
        ));
    }

    private function assertTarget(object $art, string $type, string $ref): void
    {
        if ($type === 'general') {
            return;
        }if ($type === 'field' && ! in_array($ref, self::FIELDS, true)) {
            throw new DomainException('Unsupported artwork field reference.');
        }if ($type === 'media' && ! DB::table('exhibition_artwork_media')->where('artwork_id', $art->id)->where('public_id', $ref)->exists()) {
            throw new DomainException('Media comment target must belong to the artwork.');
        }if ($type === 'evidence' && ! DB::table('exhibition_artwork_evidence')->where('artwork_id', $art->id)->where('public_id', $ref)->exists() && ! DB::table('exhibition_artwork_pricing_evidence')->where('artwork_id', $art->id)->where('public_id', $ref)->exists()) {
            throw new DomainException('Evidence comment target must belong to the artwork.');
        }
    }

    private function event(object $round, int $actor, string $type, array $payload): void
    {
        $key = hash('sha256', $round->public_id.'|'.$type.'|'.json_encode($payload, JSON_THROW_ON_ERROR));
        DB::table('exhibition_artwork_review_events')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'round_id' => $round->id, 'event_key' => $key, 'event_type' => $type, 'actor_user_id' => $actor, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
