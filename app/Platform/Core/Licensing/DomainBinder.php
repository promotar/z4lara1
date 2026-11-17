<?php

namespace App\Platform\Core\Licensing;

use App\Platform\Core\Models\License;
use App\Platform\Core\Repositories\LicenseRepository;

class DomainBinder
{
    public function __construct(
        private readonly LicenseRepository $licenses,
        private readonly LicenseValidator $validator,
    ) {
        //
    }

    public function bind(License $license, string $domain): bool
    {
        if (! $this->validator->hasValidFormat($license->license_key)) {
            return false;
        }

        $domain = $this->normalizeDomain($domain);

        if ($domain === null) {
            return false;
        }

        $currentDomain = $this->normalizeDomain($license->domain);

        if ($currentDomain !== null && $currentDomain !== $domain && ! (bool) data_get($license->metadata, 'allow_domain_rebind', false)) {
            return false;
        }

        $this->licenses->update($license, [
            'domain' => $domain,
            'status' => License::STATUS_VALID,
            'activated_at' => $license->activated_at ?? now(),
            'last_checked_at' => now(),
        ]);

        return true;
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        $host = parse_url($domain, PHP_URL_HOST);
        $value = is_string($host) ? $host : $domain;

        return strtolower(trim($value));
    }
}
