<?php

namespace Modules\Blog\Support;

use App\Platform\Core\Localization\PlatformLocale;
use Throwable;

/**
 * The "base" language of Blog content is the site language (general.site_language),
 * not the platform hard-coded default. On an Arabic site the Arabic tab is the
 * article itself and other languages are optional translations.
 */
final class BlogLocale
{
    public static function default(): string
    {
        try {
            return app(PlatformLocale::class)->defaultCode();
        } catch (Throwable) {
            return PlatformLocale::DEFAULT;
        }
    }

    public static function isDefault(string $locale): bool
    {
        return $locale === self::default();
    }
}