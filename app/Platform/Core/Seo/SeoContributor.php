<?php

namespace App\Platform\Core\Seo;

use Illuminate\Http\Request;

/**
 * Optional contract implemented by active modules.
 *
 * A module registers its contributor from its own service provider. When the
 * module is inactive its provider is not loaded, so no contributor exists and
 * the module has no effect on SEO.
 */
interface SeoContributor
{
    public function module(): string;

    public function supports(Request $request): bool;

    public function contribute(SeoData $seo, Request $request): void;
}
