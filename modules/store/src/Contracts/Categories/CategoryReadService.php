<?php

namespace Modules\Store\Contracts\Categories;

interface CategoryReadService
{
    /** @return list<CategoryView> */
    public function tree(string $locale, string $fallbackLocale, bool $includeInactive = false): array;
}
