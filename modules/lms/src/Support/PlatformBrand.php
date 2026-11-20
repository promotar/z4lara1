<?php

namespace Modules\Lms\Support;

use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves the platform site name from the canonical core settings so the LMS
 * plugin never hard-codes a brand name. The platform SettingsRepository service
 * is consumed read-only; nothing outside this plugin is modified.
 */
final class PlatformBrand
{
    private static ?string $memo = null;

    public static function name(): string
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $values = app(SettingsRepository::class)->values();
            $name = trim((string) ($values['general.site_title'] ?? ''));
            if ($name !== '') {
                return self::$memo = $name;
            }
        } catch (Throwable) {
            // Settings may be unavailable during installation or early migrations; fall back to the hosting site's app name.
        }

        return self::$memo = (trim((string) config('app.name')) ?: 'LMS');
    }

    public static function initial(): string
    {
        return Str::upper(Str::substr(self::name(), 0, 1));
    }

    public static function forget(): void
    {
        self::$memo = null;
    }
}
