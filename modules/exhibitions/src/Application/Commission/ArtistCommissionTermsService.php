<?php

namespace Modules\Exhibition\Application\Commission;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;

final readonly class ArtistCommissionTermsService
{
    public function __construct(
        private AuditRecorder $audit,
        private CoreUserGateway $users,
    ) {}

    public function save(
        string $applicationPublicId,
        string $source,
        ?string $customRate,
        string $effectiveFrom,
        string $reason,
        int $actorUserId,
    ): object {
        $artist = $this->artist($applicationPublicId);
        $source = trim($source);
        $reason = trim($reason);

        if (! in_array($source, ['platform_default', 'custom'], true)) {
            throw new DomainException('Choose the platform default or a custom contractual commission.');
        }
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 1000) {
            throw new DomainException('A contractual reason between 8 and 1,000 characters is required.');
        }

        $basisPoints = $source === 'custom' ? $this->basisPoints((string) $customRate) : null;
        $effectiveAt = Carbon::parse($effectiveFrom)->seconds(0);

        return DB::transaction(function () use ($artist, $source, $basisPoints, $effectiveAt, $reason, $actorUserId): object {
            $id = DB::table('exhibition_artist_commission_terms')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'artist_identity_id' => $artist->identity_id,
                'source' => $source,
                'commission_basis_points' => $basisPoints,
                'effective_from' => $effectiveAt,
                'reason' => $reason,
                'created_by' => $actorUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.artist.commission_terms_created',
                targetType: 'artist_identity',
                targetId: (string) $artist->identity_public_id,
                newValues: [
                    'source' => $source,
                    'commission_basis_points' => $basisPoints,
                    'effective_from' => $effectiveAt->toDateTimeString(),
                ],
                reason: $reason,
                actorUserId: $actorUserId,
            ));

            return DB::table('exhibition_artist_commission_terms')->where('id', $id)->first();
        });
    }

    public function artist(string $applicationPublicId): object
    {
        $artist = DB::table('exhibition_artist_applications as application')
            ->join('exhibition_artist_identities as identity', 'identity.core_user_id', '=', 'application.core_user_id')
            ->where('application.public_id', $applicationPublicId)
            ->select([
                'application.public_id as application_public_id',
                'application.artistic_name',
                'application.legal_name',
                'application.contact_email',
                'identity.id as identity_id',
                'identity.public_id as identity_public_id',
                'identity.core_user_id',
                'identity.state',
            ])
            ->first() ?? abort(404);

        $artist->user_name = $this->users->find((int) $artist->core_user_id)?->name;

        return $artist;
    }

    private function basisPoints(string $percentage): int
    {
        $percentage = trim($percentage);
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,3}))?$/', $percentage, $parts) !== 1) {
            throw new DomainException('Custom commission must be a percentage with up to three decimal places.');
        }
        $thousandths = ((int) $parts[1] * 1000) + (int) str_pad($parts[2] ?? '', 3, '0');
        if ($thousandths < 0 || $thousandths > 100000) {
            throw new DomainException('Custom commission must be between 0 and 100 percent.');
        }

        return intdiv($thousandths + 5, 10);
    }
}
