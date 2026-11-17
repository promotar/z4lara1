<?php

namespace App\Platform\Core\Licensing;

use App\Platform\Core\Models\License;

class LicenseValidator
{
    public function isValid(License $license, string $productType, string $productSlug, ?string $domain = null): bool
    {
        if (! $this->hasValidFormat($license->license_key)) {
            return false;
        }

        if ($license->product_type !== $productType || $license->product_slug !== $productSlug) {
            return false;
        }

        if ($license->status !== License::STATUS_VALID) {
            return false;
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return false;
        }

        return $this->domainMatches($license, $domain ?? $this->currentDomain());
    }

    public function hasValidFormat(string $licenseKey): bool
    {
        return preg_match('/^[A-Z0-9][A-Z0-9-]{7,}$/i', $licenseKey) === 1;
    }

    private function domainMatches(License $license, ?string $domain): bool
    {
        if ($license->domain === null || $license->domain === '') {
            return true;
        }

        $current = $this->normalizeDomain($domain);
        $bound = $this->normalizeDomain($license->domain);

        if ($this->isLocalDomain($current)) {
            return true;
        }

        return $current !== null && $current === $bound;
    }

    private function currentDomain(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) ? $host : null;
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

    private function isLocalDomain(?string $domain): bool
    {
        return in_array($domain, ['localhost', '127.0.0.1', '::1'], true);
    }
}
