<?php

namespace App\Platform\Core\Licensing;

use App\Platform\Core\Models\License;
use App\Platform\Core\Repositories\LicenseRepository;
use App\Platform\Core\Repositories\PluginRepository;

class LicenseManager
{
    public function __construct(
        private readonly LicenseRepository $licenses,
        private readonly PluginRepository $plugins,
        private readonly LicenseValidator $validator,
        private readonly DomainBinder $domains,
        private readonly LicenseRestrictionChecker $restrictions,
    ) {
        //
    }

    public function validate(string $licenseKey, string $productType, string $productSlug): bool
    {
        $license = $this->licenses->findByKey($licenseKey);

        return $license !== null && $this->validator->isValid($license, $productType, $productSlug);
    }

    public function activate(string $licenseKey, string $domain): bool
    {
        $license = $this->licenses->findByKey($licenseKey);

        return $license !== null && $this->domains->bind($license, $domain);
    }

    public function deactivate(string $licenseKey): bool
    {
        $license = $this->licenses->findByKey($licenseKey);

        if ($license === null) {
            return false;
        }

        $this->licenses->deactivate($license);

        return true;
    }

    public function isValidFor(string $productType, string $productSlug): bool
    {
        foreach ($this->licenses->findForProduct($productType, $productSlug) as $license) {
            if ($this->validator->isValid($license, $productType, $productSlug)) {
                return true;
            }
        }

        return false;
    }

    public function canActivatePlugin(string $pluginSlug): bool
    {
        $plugin = $this->plugins->findBySlug($pluginSlug);

        return $plugin === null
            ? false
            : $this->restrictions->isAllowed('plugin', $plugin->slug, $plugin->manifest ?? []);
    }

    public function canUpdatePlugin(string $pluginSlug): bool
    {
        return $this->canActivatePlugin($pluginSlug);
    }

    public function canUpdateTheme(string $themeSlug): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): License
    {
        return $this->licenses->create($attributes);
    }
}
