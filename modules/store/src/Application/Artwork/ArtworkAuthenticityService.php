<?php

namespace Modules\Store\Application\Artwork;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Throwable;

final readonly class ArtworkAuthenticityService
{
    private const OWNER_ROLES = ['creation_stage', 'signature', 'back', 'authenticity_certificate', 'other_requested'];

    private const AUTHORIZED_ROLES = ['purchase_proof', 'sale_authorization', 'ownership_transfer', 'prior_ownership', 'authenticity_certificate', 'other_requested'];

    public function __construct(
        private ArtworkProductService $artworks,
        private PublicIdGenerator $ids,
    ) {}

    /** @return array<string, list<string>> */
    public function rolesByBasis(): array
    {
        return ['owner' => self::OWNER_ROLES, 'authorized' => self::AUTHORIZED_ROLES];
    }

    /** @return list<string> */
    public function rolesForBasis(string $basis): array
    {
        return $this->rolesByBasis()[$basis] ?? [];
    }

    public function workspace(int $actorUserId, string $productPublicId, bool $allowAnyOwner = false): array
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);

        return [
            'artwork' => $artwork,
            'declaration' => DB::table('store_artwork_authenticity')->where('artwork_detail_id', $artwork->artwork_detail_id)->first(),
            'evidence' => DB::table('store_artwork_evidence')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->orderBy('uploaded_at')->get(),
            'provenance' => DB::table('store_artwork_provenance')->where('artwork_detail_id', $artwork->artwork_detail_id)->orderBy('event_date')->orderBy('id')->get(),
            'reviews' => DB::table('store_artwork_authenticity_reviews')->where('artwork_detail_id', $artwork->artwork_detail_id)->orderBy('occurred_at')->get(),
        ];
    }

    public function saveDeclaration(int $actorUserId, string $productPublicId, array $data, bool $allowAnyOwner = false): object
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        $basis = (string) ($data['ownership_basis'] ?? '');
        if (! in_array($basis, ['owner', 'authorized'], true)) {
            throw new DomainException('Choose whether the artwork is owned or authorized for sale.');
        }
        foreach (['inspiration_disclosure', 'digital_tools_disclosure', 'current_physical_location'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new DomainException('All authenticity disclosures and the current physical location are required.');
            }
        }
        $existing = DB::table('store_artwork_authenticity')->where('artwork_detail_id', $artwork->artwork_detail_id)->first();
        $values = [
            'ownership_basis' => $basis,
            'may_display_and_sell' => true,
            'inspiration_disclosure' => trim((string) $data['inspiration_disclosure']),
            'digital_tools_disclosure' => trim((string) $data['digital_tools_disclosure']),
            'current_physical_location' => trim((string) $data['current_physical_location']),
            'state' => 'submitted',
            'declared_at' => now(),
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('store_artwork_authenticity')->where('id', $existing->id)->update($values + [
                'lock_version' => DB::raw('lock_version + 1'),
            ]);
        } else {
            DB::table('store_artwork_authenticity')->insert($values + [
                'public_id' => (string) $this->ids->generate(),
                'artwork_detail_id' => $artwork->artwork_detail_id,
                'lock_version' => 1,
                'created_at' => now(),
            ]);
        }

        return DB::table('store_artwork_authenticity')->where('artwork_detail_id', $artwork->artwork_detail_id)->first();
    }

    public function addEvidence(int $actorUserId, string $productPublicId, UploadedFile $file, string $role, bool $allowAnyOwner = false): object
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        $declaration = DB::table('store_artwork_authenticity')->where('artwork_detail_id', $artwork->artwork_detail_id)->first()
            ?? throw new DomainException('Save the authenticity declaration before adding evidence.');
        if (! in_array($role, $this->rolesForBasis((string) $declaration->ownership_basis), true)) {
            throw new DomainException('The selected evidence type does not match the ownership basis.');
        }
        $path = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $extensions = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        if (! isset($extensions[$mime]) || ! in_array(strtolower($file->getClientOriginalExtension()), $extensions[$mime], true)) {
            throw new DomainException('Evidence must be a valid PDF, JPEG, PNG, or WebP file with a matching extension.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($path) === false) {
            throw new DomainException('The evidence image is corrupted.');
        }
        if ($file->getSize() <= 0 || $file->getSize() > 20 * 1024 * 1024) {
            throw new DomainException('Evidence must not exceed 20MB.');
        }
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new DomainException('Evidence checksum could not be calculated.');
        }
        $match = DB::table('store_artwork_evidence')->where('checksum_sha256', $checksum)->where('state', 'ready')->orderBy('id')->first();
        $publicId = (string) $this->ids->generate();
        $extension = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp'));
        $storedPath = 'store/artworks/'.$productPublicId.'/evidence/'.$publicId.'.'.$extension;
        try {
            if (! Storage::disk('local')->put($storedPath, (string) file_get_contents($path))) {
                throw new DomainException('The private evidence file could not be stored.');
            }
            DB::table('store_artwork_evidence')->insert([
                'public_id' => $publicId,
                'artwork_detail_id' => $artwork->artwork_detail_id,
                'role' => $role,
                'state' => 'ready',
                'disk' => 'local',
                'path' => $storedPath,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $mime,
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'exact_match_public_id' => $match?->public_id,
                'uploaded_by' => $actorUserId,
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The evidence could not be stored safely.', previous: $exception);
        }

        return DB::table('store_artwork_evidence')->where('public_id', $publicId)->first();
    }

    public function addProvenance(int $actorUserId, string $productPublicId, array $data, bool $allowAnyOwner = false): void
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        DB::table('store_artwork_provenance')->insert([
            'public_id' => (string) $this->ids->generate(),
            'artwork_detail_id' => $artwork->artwork_detail_id,
            'event_date' => $data['event_date'],
            'event_type' => trim((string) $data['event_type']),
            'owner_name' => $this->nullable($data['owner_name'] ?? null),
            'location' => $this->nullable($data['location'] ?? null),
            'description' => trim((string) $data['description']),
            'document_reference' => $this->nullable($data['document_reference'] ?? null),
            'entered_by' => $actorUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function evidenceFor(int $actorUserId, string $productPublicId, string $evidencePublicId, bool $allowAnyOwner = false): object
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);

        return DB::table('store_artwork_evidence')
            ->where('artwork_detail_id', $artwork->artwork_detail_id)
            ->where('public_id', $evidencePublicId)
            ->where('state', 'ready')
            ->first() ?? abort(404);
    }

    private function artwork(int $actorUserId, string $productPublicId, bool $allowAnyOwner): object
    {
        return $allowAnyOwner
            ? $this->artworks->find($productPublicId)
            : $this->artworks->findOwned($actorUserId, $productPublicId);
    }

    private function assertEditable(object $artwork): void
    {
        if (! in_array($artwork->workflow_state, ['draft', 'changes_required', 'rejected'], true)) {
            throw new DomainException('Authenticity records are locked in the current artwork state.');
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
