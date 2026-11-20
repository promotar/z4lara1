<?php

namespace Modules\Exhibition\Application\Pricing;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;

final readonly class ArtworkPricingService
{
    private const STATES = ['price_submitted', 'under_review', 'evidence_required', 'adjustment_suggested', 'artist_accepted', 'approved', 'rejected'];

    private const REVIEW_ACTIONS = ['under_review', 'evidence_required', 'adjustment_suggested', 'approved', 'rejected', 'approved_exception'];

    private const EVIDENCE_ROLES = ['previous_sale', 'material_cost', 'market_reference', 'award_or_certificate', 'other'];

    public function __construct(private ArtworkService $artworks, private AuditRecorder $audit) {}

    public function evidenceRoles(): array
    {
        return self::EVIDENCE_ROLES;
    }

    public function reviewActions(): array
    {
        return self::REVIEW_ACTIONS;
    }

    public function ownerWorkspace(int $user, string $public): array
    {
        $art = $this->artworks->owned($user, $public);

        return $this->workspace($art);
    }

    public function reviewerWorkspace(string $public): array
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);

        return $this->workspace($art);
    }

    private function workspace(object $art): array
    {
        return ['artwork' => $art, 'pricing' => DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first(), 'evidence' => DB::table('exhibition_artwork_pricing_evidence')->where('artwork_id', $art->id)->orderBy('uploaded_at')->get(), 'history' => DB::table('exhibition_artwork_pricing_history')->where('artwork_id', $art->id)->orderBy('occurred_at')->orderBy('id')->get()];
    }

    public function submit(int $user, string $public, string $amount, string $currency, string $explanation, array $factors): void
    {
        $art = $this->artworks->owned($user, $public);
        $amount = $this->money($amount, $currency);
        if (trim($explanation) === '') {
            throw new DomainException('A supporting price explanation is required.');
        }$before = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
        if ($before && in_array($before->state, ['approved', 'under_review', 'artist_accepted'], true)) {
            throw new DomainException('The current pricing state does not allow an artist resubmission.');
        }$now = now();
        $values = ['state' => 'price_submitted', 'requested_amount' => $amount, 'currency' => $currency, 'artist_explanation' => trim($explanation), 'supporting_factors' => json_encode($factors, JSON_THROW_ON_ERROR), 'proposed_amount' => null, 'agreed_amount' => null, 'approved_amount_snapshot' => null, 'documented_exception' => false, 'artist_accepted_at' => null, 'reviewer_approved_at' => null, 'last_actor_user_id' => $user, 'updated_at' => $now];
        if ($before) {
            $values['lock_version'] = $before->lock_version + 1;
            DB::table('exhibition_artwork_pricing')->where('id', $before->id)->update($values);
        } else {
            DB::table('exhibition_artwork_pricing')->insert($values + ['public_id' => (string) Str::uuid(), 'artwork_id' => $art->id, 'lock_version' => 1, 'created_at' => $now]);
        }$after = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
        $this->history($art, $user, 'artist_submitted', $before, $after, 'Artist submitted requested price and explanation.', []);
        $this->audit($art, $user, 'exhibitions.artwork.price_submitted', $before, $after, 'Artist submitted requested price.');
    }

    public function syncArtworkRequestedPrice(string $public): object
    {
        $art = DB::table('exhibition_artworks as artwork')
            ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
            ->where('artwork.public_id', $public)
            ->select('artwork.*', 'identity.core_user_id as artist_core_user_id')
            ->first() ?? abort(404);
        $before = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();

        if ($art->requested_price === null) {
            if ($before && $before->requested_amount !== null) {
                return $before;
            }
            throw new DomainException('Artwork approval requires a price submitted by the artist.');
        }

        $amount = $this->money((string) $art->requested_price, (string) ($art->currency ?: 'JOD'));
        if ($before && (float) $before->requested_amount === (float) $amount) {
            return $before;
        }
        if ($before && in_array($before->state, ['approved', 'under_review', 'artist_accepted'], true)) {
            throw new DomainException('The requested price changed while pricing was locked. Request a pricing revision before approval.');
        }

        $this->submit(
            (int) $art->artist_core_user_id,
            $public,
            $amount,
            (string) ($art->currency ?: 'JOD'),
            'Price submitted with the artwork details for manual review.',
            array_filter([
                'frame_cost' => $art->frame_cost,
                'packaging_cost' => $art->packaging_cost,
                'preparation_days' => $art->preparation_days,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        );

        return DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
    }

    public function uploadEvidence(int $user, string $public, UploadedFile $file, string $role): object
    {
        $art = $this->artworks->owned($user, $public);
        if (! in_array($role, self::EVIDENCE_ROLES, true)) {
            throw new DomainException('Unsupported pricing evidence role.');
        }$path = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $map = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! isset($map[$mime]) || $file->getSize() <= 0 || $file->getSize() > 20 * 1024 * 1024) {
            throw new DomainException('Pricing evidence must be a valid PDF or image up to 20MB.');
        }$ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $mime === 'image/jpeg' ? ['jpg', 'jpeg'] : [$map[$mime]], true)) {
            throw new DomainException('Evidence extension does not match its bytes.');
        }$id = (string) Str::uuid();
        $stored = "exhibitions/artworks/$art->public_id/pricing/$id.".$map[$mime];
        Storage::disk('local')->put($stored, (string) file_get_contents($path));
        DB::table('exhibition_artwork_pricing_evidence')->insert(['public_id' => $id, 'artwork_id' => $art->id, 'role' => $role, 'disk' => 'local', 'path' => $stored, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $mime, 'size_bytes' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $path), 'uploaded_by' => $user, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('exhibition_artwork_pricing_evidence')->where('public_id', $id)->first();
    }

    public function ownerEvidence(int $user, string $public, string $evidence): object
    {
        $art = $this->artworks->owned($user, $public);

        return DB::table('exhibition_artwork_pricing_evidence')->where('artwork_id', $art->id)->where('public_id', $evidence)->first() ?? abort(404);
    }

    public function reviewerEvidence(string $public, string $evidence): object
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);

        return DB::table('exhibition_artwork_pricing_evidence')->where('artwork_id', $art->id)->where('public_id', $evidence)->first() ?? abort(404);
    }

    public function review(int $reviewer, string $public, string $action, ?string $proposed, string $reason, array $refs): void
    {
        if (! in_array($action, self::REVIEW_ACTIONS, true)) {
            throw new DomainException('Unsupported pricing review action.');
        }if (trim($reason) === '') {
            throw new DomainException('Every pricing review action requires a reason.');
        }$art = DB::table('exhibition_artworks')->where('public_id', $public)->first() ?? abort(404);
        $before = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
        if (! $before) {
            throw new DomainException('The artist must submit a price first.');
        }if ($before->state === 'approved') {
            return;
        }$allowed = ['price_submitted' => ['under_review', 'evidence_required', 'adjustment_suggested', 'approved', 'rejected', 'approved_exception'], 'under_review' => ['evidence_required', 'adjustment_suggested', 'approved', 'rejected', 'approved_exception'], 'evidence_required' => ['under_review', 'adjustment_suggested', 'rejected', 'approved_exception'], 'artist_accepted' => ['approved', 'rejected', 'approved_exception']];
        if (! in_array($action, $allowed[$before->state] ?? [], true)) {
            throw new DomainException('Invalid pricing state transition.');
        }if ($action === 'adjustment_suggested') {
            $proposed = $this->money((string) $proposed, $before->currency);
            if ($proposed === (string) $before->requested_amount) {
                throw new DomainException('An adjustment must differ from the requested price.');
            }
        } else {
            $proposed = $before->proposed_amount;
        }if ($action === 'approved' && $before->state === 'adjustment_suggested') {
            throw new DomainException('Artist acceptance is required before reviewer approval.');
        }$state = $action === 'approved_exception' ? 'approved' : $action;
        $agreed = $state === 'approved' ? ($before->state === 'artist_accepted' ? $before->agreed_amount : $before->requested_amount) : $before->agreed_amount;
        $snapshot = $state === 'approved' ? $agreed : null;
        $valid = DB::table('exhibition_artwork_pricing_evidence')->where('artwork_id', $art->id)->whereIn('public_id', $refs)->pluck('public_id')->all();
        if (count($valid) !== count(array_unique($refs))) {
            throw new DomainException('Pricing evidence references must belong to the artwork.');
        }DB::table('exhibition_artwork_pricing')->where('id', $before->id)->update(['state' => $state, 'proposed_amount' => $proposed, 'agreed_amount' => $agreed, 'approved_amount_snapshot' => $snapshot, 'documented_exception' => $action === 'approved_exception', 'reviewer_approved_at' => $state === 'approved' ? now() : null, 'last_actor_user_id' => $reviewer, 'lock_version' => $before->lock_version + 1, 'updated_at' => now()]);
        $after = DB::table('exhibition_artwork_pricing')->where('id', $before->id)->first();
        $this->history($art, $reviewer, 'reviewer_'.$action, $before, $after, $reason, $valid);
        $this->audit($art, $reviewer, 'exhibitions.artwork.price_reviewed', $before, $after, $reason);
    }

    public function acceptAdjustment(int $artist, string $public, string $reason): void
    {
        $art = $this->artworks->owned($artist, $public);
        $before = DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->first();
        if (! $before || $before->state !== 'adjustment_suggested' || $before->proposed_amount === null) {
            throw new DomainException('There is no active adjustment to accept.');
        }if (trim($reason) === '') {
            throw new DomainException('Artist acceptance requires a note.');
        }DB::table('exhibition_artwork_pricing')->where('id', $before->id)->update(['state' => 'artist_accepted', 'agreed_amount' => $before->proposed_amount, 'artist_accepted_at' => now(), 'last_actor_user_id' => $artist, 'lock_version' => $before->lock_version + 1, 'updated_at' => now()]);
        $after = DB::table('exhibition_artwork_pricing')->where('id', $before->id)->first();
        $this->history($art, $artist, 'artist_accepted_adjustment', $before, $after, $reason, []);
        $this->audit($art, $artist, 'exhibitions.artwork.price_adjustment_accepted', $before, $after, $reason);
    }

    public function isPublishable(string $public): bool
    {
        $art = DB::table('exhibition_artworks')->where('public_id', $public)->first();

        return $art && DB::table('exhibition_artwork_pricing')->where('artwork_id', $art->id)->where('state', 'approved')->whereNotNull('approved_amount_snapshot')->exists();
    }

    private function money(string $value, string $currency): string
    {
        if ($currency !== 'JOD') {
            throw new DomainException('Only the platform JOD currency is supported.');
        }if (! preg_match('/^(0|[1-9]\d{0,14})(?:\.(\d{1,3}))?$/', $value, $match) || ($match[1] === '0' && trim(($match[2] ?? ''), '0') === '')) {
            throw new DomainException('Enter a positive JOD amount with no more than three decimal places.');
        }

        return $match[1].'.'.str_pad($match[2] ?? '', 3, '0');
    }

    private function history(object $art, int $actor, string $action, ?object $before, object $after, string $reason, array $refs): void
    {
        DB::table('exhibition_artwork_pricing_history')->insert(['public_id' => (string) Str::uuid(), 'artwork_id' => $art->id, 'actor_user_id' => $actor, 'action' => $action, 'previous_state' => $before?->state, 'new_state' => $after->state, 'requested_amount' => $after->requested_amount, 'proposed_amount' => $after->proposed_amount, 'agreed_amount' => $after->agreed_amount, 'approved_amount_snapshot' => $after->approved_amount_snapshot, 'currency' => $after->currency, 'reason' => $reason, 'evidence_references' => json_encode(array_values($refs), JSON_THROW_ON_ERROR), 'before_values' => $before ? json_encode((array) $before, JSON_THROW_ON_ERROR) : null, 'after_values' => json_encode((array) $after, JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function audit(object $art, int $actor, string $action, ?object $before, object $after, string $reason): void
    {
        $this->audit->record(new AuditEntryData(action: $action, targetType: 'artwork', targetId: $art->public_id, previousValues: $before ? (array) $before : [], newValues: (array) $after, reason: $reason, actorUserId: $actor));
    }
}
