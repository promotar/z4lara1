<?php

namespace Modules\Exhibition\Application\Artworks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ArtworkTranslationService
{
    /** @var list<string> */
    public const FIELDS = [
        'title',
        'short_description',
        'detailed_description',
        'artwork_story',
        'inspiration_source',
        'care_instructions',
        'seo_title',
        'meta_description',
        'image_alt_text',
        'keywords',
        'shipping_notes',
    ];

    public function available(): bool
    {
        return Schema::hasTable('exhibition_artwork_translations');
    }

    public function resolve(int $artworkId, string $locale): ?object
    {
        if (! $this->available() || $locale === '') {
            return null;
        }

        return DB::table('exhibition_artwork_translations')
            ->where('artwork_id', $artworkId)
            ->where('locale', $locale)
            ->first();
    }

    public function translatedLocales(int $artworkId): array
    {
        if (! $this->available()) {
            return [];
        }

        return DB::table('exhibition_artwork_translations')
            ->where('artwork_id', $artworkId)
            ->pluck('locale')
            ->map(fn ($locale): string => (string) $locale)
            ->all();
    }

    public function apply(object $artwork, ?object $translation): object
    {
        if ($translation === null) {
            return $artwork;
        }

        foreach (self::FIELDS as $field) {
            $value = $translation->{$field} ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($field === 'keywords' && is_string($value)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $value))));
            }

            $artwork->{$field} = $value;
        }

        return $artwork;
    }

    /** @param array<string, mixed> $data */
    public function save(int $artworkId, string $locale, array $data, ?int $userId = null): void
    {
        if (! $this->available() || $locale === '') {
            return;
        }

        $values = ['updated_by' => $userId, 'updated_at' => now()];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($field === 'keywords' && is_array($value)) {
                $value = json_encode(array_values(array_filter(array_map('trim', $value))), JSON_UNESCAPED_UNICODE);
            }

            $values[$field] = is_string($value) ? trim($value) : $value;
        }

        $existing = DB::table('exhibition_artwork_translations')
            ->where('artwork_id', $artworkId)
            ->where('locale', $locale)
            ->exists();

        if ($existing) {
            DB::table('exhibition_artwork_translations')
                ->where('artwork_id', $artworkId)
                ->where('locale', $locale)
                ->update($values);

            return;
        }

        DB::table('exhibition_artwork_translations')->insert($values + [
            'artwork_id' => $artworkId,
            'locale' => $locale,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    public function uniqueTitle(string $title, string $locale, ?int $ignoreId = null): string
    {
        // Titles are not unique in the schema; this helper is intentionally
        // conservative and only trims the value.
        return Str::limit(trim($title), 180, '');
    }
}
