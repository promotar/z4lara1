<?php

namespace Modules\Certificates\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Certificates\Models\CertificateIssuer;

final class IssuerAccessService
{
    public function findActive(User $user): ?CertificateIssuer
    {
        if (! Schema::hasTable('certificate_issuers')) {
            return null;
        }

        return CertificateIssuer::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();
    }

    public function isActive(User $user): bool
    {
        return $this->findActive($user) !== null;
    }

    public function activeIssuer(User $user): CertificateIssuer
    {
        $issuer = $this->findActive($user);

        abort_unless($issuer, 403, 'Certificate issuer access is not active.');

        return $issuer;
    }
}
