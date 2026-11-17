<?php

namespace App\Platform\Core\Licensing;

use App\Platform\Core\Repositories\LicenseRepository;

class LicenseRestrictionChecker
{
    public function __construct(
        private readonly LicenseRepository $licenses,
        private readonly LicenseValidator $validator,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function requiresLicense(array $manifest): bool
    {
        return (bool) data_get($manifest, 'license.required', false);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function productSlug(array $manifest, string $fallbackSlug): string
    {
        $product = data_get($manifest, 'license.product');

        return is_string($product) && trim($product) !== '' ? $product : $fallbackSlug;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function isAllowed(string $productType, string $fallbackSlug, array $manifest): bool
    {
        if (! $this->requiresLicense($manifest)) {
            return true;
        }

        $productSlug = $this->productSlug($manifest, $fallbackSlug);

        foreach ($this->licenses->findForProduct($productType, $productSlug) as $license) {
            if ($this->validator->isValid($license, $productType, $productSlug)) {
                return true;
            }
        }

        return false;
    }
}
