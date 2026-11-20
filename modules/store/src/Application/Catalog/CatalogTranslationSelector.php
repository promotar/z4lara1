<?php

namespace Modules\Store\Application\Catalog;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class CatalogTranslationSelector
{
    public function __construct(
        private ConnectionInterface $database,
        private PlatformLocale $locale,
    ) {}

    public function current(): string
    {
        return $this->locale->current();
    }

    public function products(?string $locale = null): Builder
    {
        $locale ??= $this->current();

        return $this->database->table('store_product_translations as candidate')
            ->join('store_products as translated_product', 'translated_product.id', '=', 'candidate.product_id')
            ->select('candidate.product_id')
            ->selectRaw(
                'COALESCE(MIN(CASE WHEN candidate.locale = ? THEN candidate.id END), MIN(CASE WHEN candidate.locale = translated_product.default_locale THEN candidate.id END), MIN(CASE WHEN candidate.locale = ? THEN candidate.id END), MIN(candidate.id)) as translation_id',
                [$locale, $this->locale->fallback()],
            )
            ->groupBy('candidate.product_id');
    }

    public function categories(?string $locale = null): Builder
    {
        return $this->localizedIds(
            'store_category_translations',
            'category_id',
            $locale ?? $this->current(),
        );
    }

    public function attributes(?string $locale = null): Builder
    {
        return $this->localizedIds(
            'store_attribute_translations',
            'attribute_id',
            $locale ?? $this->current(),
        );
    }

    public function attributeOptions(?string $locale = null): Builder
    {
        return $this->localizedIds(
            'store_attribute_option_translations',
            'attribute_option_id',
            $locale ?? $this->current(),
        );
    }

    public function productMedia(?string $locale = null): Builder
    {
        return $this->localizedIds(
            'store_product_media_translations',
            'product_media_id',
            $locale ?? $this->current(),
        );
    }

    private function localizedIds(string $table, string $foreignKey, string $locale): Builder
    {
        return $this->database->table($table)
            ->select($foreignKey)
            ->selectRaw(
                'COALESCE(MIN(CASE WHEN locale = ? THEN id END), MIN(CASE WHEN locale = ? THEN id END), MIN(id)) as translation_id',
                [$locale, $this->locale->fallback()],
            )
            ->groupBy($foreignKey);
    }
}
