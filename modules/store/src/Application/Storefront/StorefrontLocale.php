<?php

namespace Modules\Store\Application\Storefront;

use App\Platform\Core\Localization\PlatformLocale;

final readonly class StorefrontLocale
{
    public function __construct(private PlatformLocale $locale) {}

    public function apply(): string
    {
        return $this->locale->apply();
    }
}
