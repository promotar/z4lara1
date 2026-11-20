<?php

namespace Modules\Exhibition\Application\Applications;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Verification\ActivationEligibility;
use Modules\Exhibition\Contracts\Artists\ArtistIdentityRepository;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;
use Modules\Exhibition\Domain\Applications\ApplicationState;
use Modules\Exhibition\Domain\Applications\ApplicationTransitions;
use Modules\Exhibition\Events\V1\ArtistApplicationStateChanged;
use Modules\Exhibition\Events\V1\ArtistApplicationSubmitted;
use RuntimeException;
use Spatie\Permission\Models\Role;

final readonly class ArtistApplicationService
{
    public function __construct(
        private ArtistIdentityRepository $identities,
        private AuditRecorder $audit,
        private ApplicationTransitions $transitions,
        private ApplicationNotificationService $notifications,
        private ActivationEligibility $activationEligibility,
        private CoreUserGateway $users,
    ) {}

    public function forUser(int $coreUserId): ?object
    {
        return DB::table('exhibition_artist_applications')->where('core_user_id', $coreUserId)->first();
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(int $coreUserId, array $data): object
    {
        return DB::transaction(function () use ($coreUserId, $data): object {
            $application = DB::table('exhibition_artist_applications')
                ->where('core_user_id', $coreUserId)
                ->lockForUpdate()
                ->first();

            $this->assignArtistRole($coreUserId);

            if ($application !== null && ! in_array($application->state, [
                ApplicationState::Draft->value,
                ApplicationState::DocumentsRequired->value,
                ApplicationState::Rejected->value,
            ], true)) {
                throw new DomainException('A submitted application cannot be edited as a draft.');
            }

            $now = now();
            $values = $this->formValues($data) + ['updated_at' => $now];

            if ($application === null) {
                $this->identities->createCandidateForCoreUser($coreUserId);
                $publicId = (string) Str::uuid();
                $id = DB::table('exhibition_artist_applications')->insertGetId($values + [
                    'public_id' => $publicId,
                    'core_user_id' => $coreUserId,
                    'state' => ApplicationState::Draft->value,
                    'lock_version' => 1,
                    'created_at' => $now,
                ]);
                $application = DB::table('exhibition_artist_applications')->where('id', $id)->first();
                $this->recordHistory($application, null, ApplicationState::Draft, $coreUserId, 'Draft created.', null, null);
            } else {
                DB::table('exhibition_artist_applications')->where('id', $application->id)->update(
                    $values + ['lock_version' => DB::raw('lock_version + 1')]
                );
                $application = DB::table('exhibition_artist_applications')->where('id', $application->id)->first();
            }

            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.application.draft_saved',
                targetType: 'artist_application',
                targetId: (string) $application->public_id,
                newValues: ['state' => $application->state, 'lock_version' => $application->lock_version],
                context: ['core_user_id' => $coreUserId],
            ));

            return $application;
        });
    }

    /** @param array<string, mixed> $data */
    public function submit(int $coreUserId, array $data): object
    {
        $existing = $this->forUser($coreUserId);
        $emailVerified = $this->users->find($coreUserId)?->hasVerifiedEmail() === true;

        if ($existing !== null && $existing->state === ApplicationState::Submitted->value) {
            if ($emailVerified) {
                return $this->transition(
                    (string) $existing->public_id,
                    ApplicationState::UnderReview,
                    $coreUserId,
                    'Email address was already verified. Application automatically moved to review.',
                );
            }

            return $existing;
        }

        $isVerifiedResubmission = in_array($existing?->state, [
            ApplicationState::DocumentsRequired->value,
            ApplicationState::Rejected->value,
        ], true)
            && $emailVerified;
        $draft = $this->saveDraft($coreUserId, $data);
        $targetState = $isVerifiedResubmission
            ? ApplicationState::UnderReview
            : ApplicationState::Submitted;
        $submitted = $this->transitionInternal(
            (string) $draft->public_id,
            $targetState,
            $coreUserId,
            match ($existing?->state) {
                ApplicationState::DocumentsRequired->value => 'Requested documents or information submitted. Application returned to review.',
                ApplicationState::Rejected->value => 'Application resubmitted after rejection.',
                default => 'Application submitted by applicant.',
            },
            null,
            null,
        );

        Event::dispatch(new ArtistApplicationSubmitted((string) $submitted->public_id, $coreUserId));
        $this->notifications->sendOnce($submitted, 'received');

        if ($submitted->state === ApplicationState::Submitted->value && $emailVerified) {
            return $this->transition(
                (string) $submitted->public_id,
                ApplicationState::UnderReview,
                $coreUserId,
                'Email address was already verified. Application automatically moved to review.',
            );
        }

        return $submitted;
    }

    public function transition(
        string $publicId,
        ApplicationState $to,
        int $actorUserId,
        ?string $reason = null,
        ?string $nextAction = null,
        ?string $deadline = null,
    ): object {
        $application = $this->transitionInternal($publicId, $to, $actorUserId, $reason, $nextAction, $deadline);
        $this->notifications->sendOnce($application, 'state_changed');

        return $application;
    }

    /** @param list<UploadedFile> $files */
    public function storeSamples(object $application, array $files): int
    {
        $stored = 0;
        $disk = (string) config('exhibitions.applications.sample_disk', 'local');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $name = (string) Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
            $path = 'exhibitions/applications/'.$application->public_id.'/samples/'.$name;
            $stream = fopen($file->getRealPath(), 'rb');
            if ($stream === false || ! Storage::disk($disk)->put($path, $stream)) {
                throw new RuntimeException('The sample artwork could not be stored.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }

            DB::table('exhibition_application_samples')->insert([
                'public_id' => (string) Str::uuid(),
                'application_id' => $application->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stored++;
        }

        return $stored;
    }

    /** @param list<UploadedFile> $files */
    public function storeSupportingDocuments(object $application, array $files): int
    {
        $stored = 0;
        $disk = (string) config('exhibitions.applications.sample_disk', 'local');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $name = (string) Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
            $path = 'exhibitions/applications/'.$application->public_id.'/supporting-documents/'.$name;
            $stream = fopen($file->getRealPath(), 'rb');
            if ($stream === false || ! Storage::disk($disk)->put($path, $stream)) {
                throw new RuntimeException('The supporting document could not be stored.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }

            DB::table('exhibition_application_supporting_documents')->insert([
                'public_id' => (string) Str::uuid(),
                'application_id' => $application->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stored++;
        }

        return $stored;
    }

    private function transitionInternal(
        string $publicId,
        ApplicationState $to,
        int $actorUserId,
        ?string $reason,
        ?string $nextAction,
        ?string $deadline,
    ): object {
        return DB::transaction(function () use ($publicId, $to, $actorUserId, $reason, $nextAction, $deadline): object {
            $application = DB::table('exhibition_artist_applications')
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->first();
            if ($application === null) {
                throw new RuntimeException('Artist application not found.');
            }

            $from = ApplicationState::from($application->state);
            if ($from === $to) {
                return $application;
            }
            $this->transitions->assertAllowed($from, $to, $reason, $nextAction);
            if ($to === ApplicationState::Active) {
                $this->activationEligibility->assertEligible((int) $application->id);
            }

            $values = [
                'state' => $to->value,
                'current_reason' => $reason,
                'next_action' => $nextAction,
                'response_deadline' => $deadline,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ];
            if ($to === ApplicationState::Submitted && $application->submitted_at === null) {
                $values['submitted_at'] = now();
            }
            DB::table('exhibition_artist_applications')->where('id', $application->id)->update($values);
            $updated = DB::table('exhibition_artist_applications')->where('id', $application->id)->first();
            $this->recordHistory($updated, $from, $to, $actorUserId, $reason, $nextAction, $deadline);

            if (in_array($to, [ApplicationState::Approved, ApplicationState::Active], true)) {
                $this->activateApprovedArtist((int) $updated->core_user_id, $actorUserId, $to);
            }

            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.application.state_changed',
                targetType: 'artist_application',
                targetId: (string) $updated->public_id,
                previousValues: ['state' => $from->value],
                newValues: ['state' => $to->value],
                reason: $reason,
                actorUserId: $actorUserId,
                context: ['next_action' => $nextAction, 'deadline' => $deadline],
            ));
            Event::dispatch(new ArtistApplicationStateChanged(
                (string) $updated->public_id,
                (int) $updated->core_user_id,
                $from->value,
                $to->value,
            ));

            return $updated;
        });
    }

    private function recordHistory(object $application, ?ApplicationState $from, ApplicationState $to, int $actorUserId, ?string $reason, ?string $nextAction, ?string $deadline): void
    {
        $now = now();
        DB::table('exhibition_application_status_history')->insert([
            'public_id' => (string) Str::uuid(),
            'application_id' => $application->id,
            'actor_user_id' => $actorUserId,
            'from_state' => $from?->value,
            'to_state' => $to->value,
            'reason' => $reason,
            'next_action' => $nextAction,
            'response_deadline' => $deadline,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function activateApprovedArtist(int $coreUserId, int $actorUserId, ApplicationState $applicationState): void
    {
        $identity = DB::table('exhibition_artist_identities')
            ->where('core_user_id', $coreUserId)
            ->lockForUpdate()
            ->first();

        if ($identity === null || $identity->state !== 'candidate') {
            return;
        }

        $reason = $applicationState === ApplicationState::Approved
            ? 'Artist application approved after manual review.'
            : 'Artist account activated after eligibility checks.';
        $now = now();
        $active = ArtistAccountState::Active;

        DB::table('exhibition_artist_identities')->where('id', $identity->id)->update([
            'state' => $active->value,
            'activated_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('exhibition_artist_account_status_history')->insert([
            'public_id' => (string) Str::uuid(),
            'artist_identity_id' => $identity->id,
            'actor_user_id' => $actorUserId,
            'from_state' => 'candidate',
            'to_state' => $active->value,
            'reason' => $reason,
            'capabilities' => json_encode($active->capabilities(), JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.artist_account.activated_on_approval',
            targetType: 'artist_identity',
            targetId: (string) $identity->public_id,
            previousValues: ['state' => 'candidate'],
            newValues: ['state' => $active->value, 'capabilities' => $active->capabilities()],
            reason: $reason,
            actorUserId: $actorUserId,
            context: ['application_state' => $applicationState->value],
        ));
    }

    private function assignArtistRole(int $coreUserId): void
    {
        Role::findOrCreate('artist', 'web');
        $this->users->ensureRole($coreUserId, 'artist');
        $this->users->removeRole($coreUserId, 'user');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function formValues(array $data): array
    {
        $fields = [
            'legal_name', 'birth_date', 'nationality', 'artistic_name',
            'contact_email', 'contact_phone', 'city', 'country', 'biography',
            'years_experience', 'qualifications', 'previous_exhibitions', 'awards_certificates',
        ];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $data[$field] ?? null;
        }
        $values['previous_work_links'] = json_encode(array_values(array_filter((array) ($data['previous_work_links'] ?? []))), JSON_THROW_ON_ERROR);
        $values['social_accounts'] = json_encode(array_values(array_filter((array) ($data['social_accounts'] ?? []))), JSON_THROW_ON_ERROR);
        if ((bool) ($data['ownership_declaration'] ?? false)) {
            $values['ownership_declared_at'] = now();
        }

        return $values;
    }
}
