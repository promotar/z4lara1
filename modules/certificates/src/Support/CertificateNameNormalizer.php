<?php

namespace Modules\Certificates\Support;

final class CertificateNameNormalizer
{
    public static function normalize(?string $name): string
    {
        $value = trim((string) $name);

        if ($value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            if (is_string($normalized) && $normalized !== '') {
                $value = $normalized;
            }
        }

        // Strip zero-width and directional formatting characters.
        $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $value) ?? $value;
        // Strip the Arabic tatweel and diacritics (tashkeel).
        $value = str_replace("\u{0640}", '', $value);
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        // Normalise alef variants to a bare alef.
        $value = preg_replace('/[\x{0622}\x{0623}\x{0625}\x{0671}]/u', "\u{0627}", $value) ?? $value;
        // Normalise alef maqsura / farsi yeh to yeh, and teh marbuta to heh.
        $value = str_replace(["\u{0649}", "\u{06CC}", "\u{06D2}", "\u{0629}"], ["\u{064A}", "\u{064A}", "\u{064A}", "\u{0647}"], $value);
        // Case-insensitive comparison for Latin text.
        $value = mb_strtolower($value, 'UTF-8');
        // Collapse repeated whitespace.
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
