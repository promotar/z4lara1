<?php

namespace Modules\Exhibition\Application\Authenticity;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Throwable;

final readonly class ArtworkAuthenticityService
{
    private const OWNER_ROLES = ['creation_stage', 'signature', 'back', 'authenticity_certificate', 'other_requested'];

    private const AUTHORIZED_ROLES = ['purchase_proof', 'sale_authorization', 'ownership_transfer', 'prior_ownership', 'authenticity_certificate', 'other_requested'];

    private const ROLES = ['creation_stage', 'signature', 'back', 'purchase_proof', 'sale_authorization', 'ownership_transfer', 'prior_ownership', 'authenticity_certificate', 'other_requested'];

    private const DECISIONS = ['documentation_accepted', 'additional_evidence_required', 'under_investigation', 'ownership_dispute', 'rejected'];

    public function __construct(private ArtworkService $artworks, private AuditRecorder $audit) {}

    public function roles(): array
    {
        return self::ROLES;
    }

    public function rolesByBasis(): array
    {
        return ['owner' => self::OWNER_ROLES, 'authorized' => self::AUTHORIZED_ROLES];
    }

    public function rolesForBasis(string $basis): array
    {
        return $this->rolesByBasis()[$basis] ?? [];
    }

    public function ownershipBasis(int $user, string $artworkId): ?string
    {
        $artwork = $this->artworks->owned($user, $artworkId);
        $declaration = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $artwork->id)->first();

        return $declaration ? ((bool) $declaration->owns_artwork ? 'owner' : ((bool) $declaration->may_display_and_sell ? 'authorized' : null)) : null;
    }

    public function decisions(): array
    {
        return self::DECISIONS;
    }

    public function workspace(int $user, string $artworkId): array
    {
        $artwork = $this->artworks->owned($user, $artworkId);

        return $this->forArtwork($artwork);
    }

    public function reviewerWorkspace(string $artworkId): array
    {
        $artwork = DB::table('exhibition_artworks')->where('public_id', $artworkId)->first() ?? abort(404);

        return $this->forArtwork($artwork);
    }

    private function forArtwork(object $artwork): array
    {
        return ['artwork' => $artwork, 'declaration' => DB::table('exhibition_artwork_authenticity')->where('artwork_id', $artwork->id)->first(),
            'evidence' => DB::table('exhibition_artwork_evidence')->where('artwork_id', $artwork->id)->where('state', 'ready')->orderBy('uploaded_at')->get(),
            'provenance' => DB::table('exhibition_artwork_provenance')->where('artwork_id', $artwork->id)->orderBy('event_date')->orderBy('id')->get(),
            'reviews' => DB::table('exhibition_artwork_authenticity_reviews')->where('artwork_id', $artwork->id)->orderBy('occurred_at')->get()];
    }

    public function declare(int $user, string $artworkId, array $data): void
    {
        $artwork = $this->artworks->owned($user, $artworkId);
        if (! array_key_exists('owns_artwork', $data) || ! is_bool($data['owns_artwork']) || ($data['may_display_and_sell'] ?? false) !== true) {
            throw new DomainException('Choose whether you own the artwork or are authorized by its owner to sell it.');
        }
        foreach (['inspiration_disclosure', 'digital_tools_disclosure', 'current_physical_location'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new DomainException('All disclosure and current-location fields are required. Use “None” where applicable.');
            }
        }
        $before = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $artwork->id)->first();
        $now = now();
        $values = ['owns_artwork' => $data['owns_artwork'], 'may_display_and_sell' => true, 'inspiration_disclosure' => trim($data['inspiration_disclosure']), 'digital_tools_disclosure' => trim($data['digital_tools_disclosure']), 'current_physical_location' => trim($data['current_physical_location']), 'state' => 'submitted', 'declared_at' => $now, 'updated_at' => $now];
        if ($before) {
            $values['lock_version'] = $before->lock_version + 1;
            DB::table('exhibition_artwork_authenticity')->where('id', $before->id)->update($values);
        } else {
            DB::table('exhibition_artwork_authenticity')->insert($values + ['public_id' => (string) Str::uuid(), 'artwork_id' => $artwork->id, 'lock_version' => 1, 'created_at' => $now]);
        }
        $after = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $artwork->id)->first();
        $this->audit->record(new AuditEntryData(action: 'exhibitions.artwork.authenticity_declared', targetType: 'artwork', targetId: $artwork->public_id, previousValues: $before ? (array) $before : [], newValues: (array) $after, actorUserId: $user));
    }

    public function addEvidence(int $user, string $artworkId, UploadedFile $file, string $role, ?string $basis = null): object
    {
        $artwork = $this->artworks->owned($user, $artworkId);
        if (! in_array($role, self::ROLES, true)) {
            throw new DomainException('Unsupported evidence role.');
        }
        $basis ??= $this->ownershipBasis($user, $artworkId);
        if ($basis === null) {
            throw new DomainException('Choose your relationship to the artwork before adding evidence.');
        }
        if (! in_array($role, $this->rolesForBasis($basis), true)) {
            throw new DomainException('The selected evidence type does not match your relationship to the artwork.');
        }
        $path = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! isset($allowed[$mime])) {
            throw new DomainException('Evidence must be a valid PDF, JPEG, PNG, or WebP file.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($path) === false) {
            throw new DomainException('The evidence image is corrupted or does not contain valid image pixels.');
        }
        if ($file->getSize() <= 0 || $file->getSize() > 20 * 1024 * 1024) {
            throw new DomainException('Evidence must not exceed 20MB.');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $mime === 'image/jpeg' ? ['jpg', 'jpeg'] : [$allowed[$mime]], true)) {
            throw new DomainException('Evidence filename extension does not match its bytes.');
        }
        $hash = hash_file('sha256', $path);
        $match = DB::table('exhibition_artwork_evidence')->where('checksum_sha256', $hash)->where('state', 'ready')->orderBy('id')->first();
        $public = (string) Str::uuid();
        $stored = 'exhibitions/artworks/'.$artwork->public_id.'/evidence/'.$public.'.'.$allowed[$mime];
        try {
            if (! Storage::disk('local')->put($stored, (string) file_get_contents($path))) {
                throw new DomainException('The private evidence file could not be stored.');
            }
            DB::transaction(function () use ($public, $artwork, $role, $stored, $file, $mime, $hash, $match, $user): void {
                DB::table('exhibition_artwork_evidence')->insert(['public_id' => $public, 'artwork_id' => $artwork->id, 'role' => $role, 'state' => 'ready', 'disk' => 'local', 'path' => $stored, 'original_name' => Str::limit($file->getClientOriginalName(), 500, ''), 'mime_type' => $mime, 'size_bytes' => $file->getSize(), 'checksum_sha256' => $hash, 'exact_match_public_id' => $match?->public_id, 'uploaded_by' => $user, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $this->audit->record(new AuditEntryData(action: 'exhibitions.artwork.evidence_uploaded', targetType: 'artwork_evidence', targetId: $public, newValues: ['artwork' => $artwork->public_id, 'role' => $role, 'same_file_previously_uploaded' => (bool) $match], actorUserId: $user));
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            DB::table('exhibition_artwork_evidence')->where('public_id', $public)->delete();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The evidence could not be stored safely. Please retry.', previous: $exception);
        }
        $record = DB::table('exhibition_artwork_evidence')->where('public_id', $public)->first();

        return $record;
    }

    public function evidenceForOwner(int $user, string $artworkId, string $public): object
    {
        $artwork = $this->artworks->owned($user, $artworkId);

        return DB::table('exhibition_artwork_evidence')->where('artwork_id', $artwork->id)->where('public_id', $public)->where('state', 'ready')->first() ?? abort(404);
    }

    public function evidenceForReviewer(string $artworkId, string $public): object
    {
        $artwork = DB::table('exhibition_artworks')->where('public_id', $artworkId)->first() ?? abort(404);

        return DB::table('exhibition_artwork_evidence')->where('artwork_id', $artwork->id)->where('public_id', $public)->where('state', 'ready')->first() ?? abort(404);
    }

    public function addProvenance(int $user, string $artworkId, array $data): void
    {
        $artwork = $this->artworks->owned($user, $artworkId);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data['event_date'] ?? '')) || trim((string) ($data['description'] ?? '')) === '' || trim((string) ($data['event_type'] ?? '')) === '') {
            throw new DomainException('A valid date, event type, and description are required.');
        }
        DB::table('exhibition_artwork_provenance')->insert(['public_id' => (string) Str::uuid(), 'artwork_id' => $artwork->id, 'event_date' => $data['event_date'], 'event_type' => Str::limit(trim($data['event_type']), 64, ''), 'owner_name' => Str::limit(trim((string) ($data['owner_name'] ?? '')), 255, '') ?: null, 'location' => Str::limit(trim((string) ($data['location'] ?? '')), 500, '') ?: null, 'description' => trim($data['description']), 'document_reference' => Str::limit(trim((string) ($data['document_reference'] ?? '')), 500, '') ?: null, 'entered_by' => $user, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record(new AuditEntryData(action: 'exhibitions.artwork.provenance_added', targetType: 'artwork', targetId: $artwork->public_id, newValues: ['event_date' => $data['event_date'], 'event_type' => $data['event_type']], actorUserId: $user));
    }

    public function review(int $reviewer, string $artworkId, string $decision, string $reason, ?string $notes, ?string $instructions, array $references): void
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw new DomainException('Unsupported authenticity decision.');
        }if (trim($reason) === '') {
            throw new DomainException('Every authenticity decision requires a reason.');
        }if ($decision === 'additional_evidence_required' && trim((string) $instructions) === '') {
            throw new DomainException('Evidence requests require clear instructions.');
        }
        $artwork = DB::table('exhibition_artworks')->where('public_id', $artworkId)->first() ?? abort(404);
        $declaration = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $artwork->id)->first();
        if (! $declaration) {
            throw new DomainException('Artist declarations must exist before review.');
        }
        $valid = DB::table('exhibition_artwork_evidence')->where('artwork_id', $artwork->id)->whereIn('public_id', $references)->pluck('public_id')->all();
        if (count($valid) !== count(array_unique($references))) {
            throw new DomainException('Evidence references must belong to the reviewed artwork.');
        }
        $version = ((int) DB::table('exhibition_artwork_authenticity_reviews')->where('artwork_id', $artwork->id)->max('review_version')) + 1;
        DB::transaction(function () use ($artwork, $declaration, $reviewer, $decision, $reason, $notes, $instructions, $valid, $version) {
            DB::table('exhibition_artwork_authenticity_reviews')->insert(['public_id' => (string) Str::uuid(), 'artwork_id' => $artwork->id, 'reviewer_user_id' => $reviewer, 'previous_state' => $declaration->state, 'decision' => $decision, 'reason' => trim($reason), 'review_notes' => trim((string) $notes) ?: null, 'request_instructions' => trim((string) $instructions) ?: null, 'evidence_references' => json_encode(array_values($valid), JSON_THROW_ON_ERROR), 'review_version' => $version, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('exhibition_artwork_authenticity')->where('id', $declaration->id)->update(['state' => $decision, 'lock_version' => $declaration->lock_version + 1, 'updated_at' => now()]);
        });
        $this->audit->record(new AuditEntryData(action: 'exhibitions.artwork.authenticity_reviewed', targetType: 'artwork', targetId: $artworkId, previousValues: ['state' => $declaration->state], newValues: ['state' => $decision, 'evidence_references' => $valid], reason: $reason, actorUserId: $reviewer));
    }
}
