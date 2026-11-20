<?php

namespace Modules\Exhibition\Application\Verification;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Domain\Verification\VerificationState;
use RuntimeException;

final readonly class ArtistVerificationService
{
    public function __construct(private AuditRecorder $audit) {}

    public function forApplication(object $application): object
    {
        $case = DB::table('exhibition_verification_cases')->where('application_id', $application->id)->first();
        if ($case !== null) {
            $this->assignDefaultAgreementIfAvailable($case);

            return $case;
        }

        $now = now();
        $id = DB::table('exhibition_verification_cases')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'application_id' => $application->id,
            'state' => VerificationState::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $case = DB::table('exhibition_verification_cases')->where('id', $id)->first();
        $this->assignDefaultAgreementIfAvailable($case);

        return $case;
    }

    public function defaultAgreementTemplate(): ?object
    {
        if (! $this->supportsDefaultAgreements()) {
            return null;
        }

        return DB::table('exhibition_agreement_templates')
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderByDesc('published_at')
            ->first();
    }

    public function activeAgreementTemplate(string $publicId): object
    {
        return DB::table('exhibition_agreement_templates')
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->first() ?? throw new DomainException('The selected artist agreement is unavailable.');
    }

    public function activeAgreementTemplateRule(): object
    {
        return Rule::exists('exhibition_agreement_templates', 'public_id')->where('is_active', true);
    }

    /** @param list<UploadedFile> $files */
    public function storeDocuments(object $case, string $type, array $files, int $actorUserId): int
    {
        if (! in_array($type, [
            'identity',
            'identity_national_front',
            'identity_national_back',
            'identity_passport',
            'identity_license_front',
            'identity_license_back',
            'additional',
        ], true)) {
            throw new DomainException('Unsupported verification document type.');
        }

        $stored = 0;
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            DB::transaction(function () use ($case, $type, $file, $actorUserId, &$stored): void {
                $current = DB::table('exhibition_verification_documents')
                    ->where('verification_case_id', $case->id)
                    ->where('type', $type)
                    ->where('status', 'current')
                    ->lockForUpdate()
                    ->first();
                $version = (int) DB::table('exhibition_verification_documents')
                    ->where('verification_case_id', $case->id)
                    ->where('type', $type)
                    ->max('version') + 1;
                $disk = (string) config('exhibitions.verification.document_disk', 'local');
                $extension = strtolower($file->getClientOriginalExtension());
                $path = 'exhibitions/verification/'.$case->public_id.'/'.$type.'/'.Str::uuid().'.'.$extension;
                $stream = fopen($file->getRealPath(), 'rb');
                if ($stream === false || ! Storage::disk($disk)->put($path, $stream)) {
                    throw new RuntimeException('The private verification document could not be stored.');
                }
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $now = now();
                if ($current !== null) {
                    DB::table('exhibition_verification_documents')->where('id', $current->id)->update([
                        'status' => 'superseded', 'superseded_at' => $now, 'updated_at' => $now,
                    ]);
                }
                DB::table('exhibition_verification_documents')->insert([
                    'public_id' => (string) Str::uuid(), 'verification_case_id' => $case->id,
                    'type' => $type, 'version' => $version, 'status' => 'current',
                    'replaces_document_id' => $current?->id, 'disk' => $disk, 'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'mime_type' => (string) $file->getMimeType(), 'size_bytes' => $file->getSize(),
                    'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $actorUserId, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $stored++;
            });
        }

        if ($stored > 0) {
            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.verification.documents_uploaded', targetType: 'verification_case',
                targetId: (string) $case->public_id, newValues: ['type' => $type, 'count' => $stored], actorUserId: $actorUserId,
            ));
        }

        return $stored;
    }

    /** @param array<string, bool> $checks */
    public function decide(object $case, VerificationState $state, array $checks, int $reviewerId, string $reason, ?string $instructions = null, ?string $deadline = null): object
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('An auditable decision reason is required.');
        }
        if (! in_array($state, [VerificationState::DocumentsRequired, VerificationState::Verified, VerificationState::Rejected], true)) {
            throw new DomainException('Unsupported verification decision.');
        }
        if ($state === VerificationState::DocumentsRequired && trim((string) $instructions) === '') {
            throw new DomainException('Due instructions are required when requesting documents.');
        }
        if ($state === VerificationState::Verified) {
            $hasIdentity = DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->where('type', 'like', 'identity%')->where('status', 'current')->exists();
            if (! $hasIdentity || ! ($checks['contact'] ?? false) || ! ($checks['profile'] ?? false) || ! ($checks['samples'] ?? false)) {
                throw new DomainException('Identity, contact, artistic profile, and sample-work checks must all be complete.');
            }
        }

        $now = now();
        DB::table('exhibition_verification_cases')->where('id', $case->id)->update([
            'state' => $state->value,
            'contact_verified_at' => ($checks['contact'] ?? false) ? ($case->contact_verified_at ?: $now) : $case->contact_verified_at,
            'artistic_profile_verified_at' => ($checks['profile'] ?? false) ? ($case->artistic_profile_verified_at ?: $now) : $case->artistic_profile_verified_at,
            'samples_verified_at' => ($checks['samples'] ?? false) ? ($case->samples_verified_at ?: $now) : $case->samples_verified_at,
            'decision_reason' => $reason, 'requested_instructions' => $instructions,
            'response_deadline' => $deadline, 'decided_by' => $reviewerId, 'decided_at' => $now,
            'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now,
        ]);
        $updated = DB::table('exhibition_verification_cases')->where('id', $case->id)->first();
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.verification.decision', targetType: 'verification_case', targetId: (string) $case->public_id,
            previousValues: ['state' => $case->state], newValues: ['state' => $state->value], reason: $reason,
            actorUserId: $reviewerId, context: ['instructions' => $instructions, 'deadline' => $deadline],
        ));

        return $updated;
    }

    /** @param array<string, string> $terms */
    public function publishTemplate(string $version, string $title, string $content, int $commissionBasisPoints, array $terms, int $reviewerId): object
    {
        $values = [
            'public_id' => (string) Str::uuid(), 'version' => $version, 'title' => $title, 'content' => $content,
            'commission_basis_points' => $commissionBasisPoints, 'terms' => json_encode($terms, JSON_THROW_ON_ERROR),
            'created_by' => $reviewerId, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ];
        if ($this->supportsDefaultAgreements()) {
            $values += ['is_active' => true, 'is_default' => false];
        }
        $id = DB::table('exhibition_agreement_templates')->insertGetId($values);

        return DB::table('exhibition_agreement_templates')->where('id', $id)->first();
    }

    public function setDefaultTemplate(object $template, int $reviewerId): object
    {
        if (! $this->supportsDefaultAgreements()) {
            throw new DomainException('Agreement template defaults are not available until the module migration is applied.');
        }

        DB::transaction(function () use ($template): void {
            DB::table('exhibition_agreement_templates')->where('is_default', true)->update(['is_default' => false, 'updated_at' => now()]);
            DB::table('exhibition_agreement_templates')->where('id', $template->id)->update([
                'is_active' => true,
                'is_default' => true,
                'updated_at' => now(),
            ]);
        });

        $default = DB::table('exhibition_agreement_templates')->where('id', $template->id)->first();
        $cases = DB::table('exhibition_verification_cases as cases')
            ->leftJoin('exhibition_artist_agreements as agreements', 'agreements.verification_case_id', '=', 'cases.id')
            ->whereNull('agreements.accepted_at')
            ->select('cases.*')
            ->get();
        foreach ($cases as $case) {
            $this->assignAgreement($case, $default, $reviewerId);
        }

        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.agreement_template.default_selected',
            targetType: 'agreement_template',
            targetId: (string) $default->public_id,
            newValues: ['version' => $default->version, 'is_default' => true],
            actorUserId: $reviewerId,
        ));

        return $default;
    }

    public function setTemplateActive(object $template, bool $active, int $reviewerId): object
    {
        if (! $this->supportsDefaultAgreements()) {
            throw new DomainException('Agreement template states are not available until the module migration is applied.');
        }
        if (! $active && (bool) $template->is_default) {
            throw new DomainException('Select another default agreement before archiving this version.');
        }

        DB::table('exhibition_agreement_templates')->where('id', $template->id)->update(['is_active' => $active, 'updated_at' => now()]);
        $updated = DB::table('exhibition_agreement_templates')->where('id', $template->id)->first();
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.agreement_template.state_changed',
            targetType: 'agreement_template',
            targetId: (string) $updated->public_id,
            previousValues: ['is_active' => (bool) $template->is_active],
            newValues: ['is_active' => $active],
            actorUserId: $reviewerId,
        ));

        return $updated;
    }

    public function assignAgreement(object $case, object $template, ?int $reviewerId): object
    {
        $existing = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first();
        if ($existing !== null && (int) $existing->template_id === (int) $template->id) {
            return $existing;
        }
        if ($existing !== null && $existing->accepted_at !== null) {
            throw new DomainException('An accepted agreement is immutable and cannot be replaced.');
        }
        $values = [
            'template_id' => $template->id, 'accepted_version' => $template->version,
            'accepted_title' => $template->title, 'accepted_content' => $template->content,
            'commission_basis_points' => $template->commission_basis_points,
            'accepted_terms' => $template->terms, 'assigned_at' => now(), 'updated_at' => now(),
        ];
        if ($existing === null) {
            $id = DB::table('exhibition_artist_agreements')->insertGetId($values + [
                'public_id' => (string) Str::uuid(), 'verification_case_id' => $case->id, 'created_at' => now(),
            ]);
        } else {
            DB::table('exhibition_artist_agreements')->where('id', $existing->id)->update($values + [
                'accepted_at' => null, 'accepted_by' => null, 'acceptance_method' => null, 'acceptance_evidence' => null,
            ]);
            $id = $existing->id;
        }
        $assigned = DB::table('exhibition_artist_agreements')->where('id', $id)->first();
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.agreement.assigned', targetType: 'artist_agreement', targetId: (string) $assigned->public_id,
            newValues: ['version' => $template->version], actorUserId: $reviewerId,
        ));

        return $assigned;
    }

    private function assignDefaultAgreementIfAvailable(object $case): ?object
    {
        if (! $this->supportsDefaultAgreements()) {
            return null;
        }

        $existing = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first();
        if ($existing !== null && $existing->accepted_at !== null) {
            return $existing;
        }

        $template = DB::table('exhibition_agreement_templates')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
        if ($template === null) {
            return $existing;
        }

        return $this->assignAgreement($case, $template, null);
    }

    private function supportsDefaultAgreements(): bool
    {
        return Schema::hasColumn('exhibition_agreement_templates', 'is_active')
            && Schema::hasColumn('exhibition_agreement_templates', 'is_default');
    }

    /** @param array<string, string|null> $evidence */
    public function acceptAgreement(object $case, int $artistUserId, array $evidence): object
    {
        $agreement = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->lockForUpdate()->first();
        if ($agreement === null) {
            throw new DomainException('No agreement has been assigned.');
        }
        if ($agreement->accepted_at !== null) {
            return $agreement;
        }
        DB::table('exhibition_artist_agreements')->where('id', $agreement->id)->update([
            'accepted_at' => now(), 'accepted_by' => $artistUserId, 'acceptance_method' => 'authenticated_web_checkbox',
            'acceptance_evidence' => json_encode($evidence, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
        $accepted = DB::table('exhibition_artist_agreements')->where('id', $agreement->id)->first();
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.agreement.accepted', targetType: 'artist_agreement', targetId: (string) $accepted->public_id,
            newValues: ['version' => $accepted->accepted_version, 'method' => $accepted->acceptance_method], actorUserId: $artistUserId,
        ));

        return $accepted;
    }

    /** @param array<string, string> $details */
    public function savePayout(object $case, string $method, array $details, int $artistUserId): object
    {
        $identifier = (string) ($details['account_identifier'] ?? '');
        $masked = in_array($method, ['check', 'cash'], true)
            ? 'Company headquarters pickup'
            : Str::mask($identifier, '*', 0, max(0, strlen($identifier) - 4));
        $values = [
            'method' => $method, 'encrypted_payload' => Crypt::encryptString(json_encode($details, JSON_THROW_ON_ERROR)),
            'masked_identifier' => $masked, 'submitted_by' => $artistUserId, 'submitted_at' => now(),
            'verified_by' => null, 'verified_at' => null, 'updated_at' => now(),
        ];
        $existing = DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->first();
        if ($existing === null) {
            $id = DB::table('exhibition_payout_profiles')->insertGetId($values + [
                'public_id' => (string) Str::uuid(), 'verification_case_id' => $case->id, 'created_at' => now(),
            ]);
        } else {
            DB::table('exhibition_payout_profiles')->where('id', $existing->id)->update($values);
            $id = $existing->id;
        }

        return DB::table('exhibition_payout_profiles')->where('id', $id)->first();
    }

    public function verifyPayout(object $case, int $reviewerId): object
    {
        $profile = DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->first();
        if ($profile === null) {
            throw new DomainException('Payout information must be submitted first.');
        }
        DB::table('exhibition_payout_profiles')->where('id', $profile->id)->update(['verified_by' => $reviewerId, 'verified_at' => now(), 'updated_at' => now()]);
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.payout.verified', targetType: 'payout_profile', targetId: (string) $profile->public_id,
            newValues: ['verified' => true], actorUserId: $reviewerId,
        ));

        return DB::table('exhibition_payout_profiles')->where('id', $profile->id)->first();
    }
}
