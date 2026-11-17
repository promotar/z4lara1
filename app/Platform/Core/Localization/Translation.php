<?php

namespace App\Platform\Core\Localization;

/**
 * Resolves a translation key to its bilingual pair without leaking the raw
 * key when a human readable fallback is available (dynamic database labels,
 * plugin menu labels, role names, status values, and so on).
 */
final class Translation
{
    public static function label(string $key, ?string $fallback = null): string
    {
        try {
            $translator = app('translator');

            if ($translator->has($key)) {
                return (string) $translator->get($key);
            }
        } catch (\Throwable) {
            // Fall through to the provided fallback.
        }

        return $fallback ?? $key;
    }

    public static function keyFor(string $group, string $value): string
    {
        $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''));

        return $group.'.'.trim($normalized, '-');
    }
}
