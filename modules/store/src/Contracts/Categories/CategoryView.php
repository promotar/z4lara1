<?php

namespace Modules\Store\Contracts\Categories;

final readonly class CategoryView
{
    public function __construct(
        public string $publicId,
        public ?string $parentPublicId,
        public string $status,
        public int $depth,
        public int $position,
        public string $locale,
        public bool $usedFallback,
        public string $name,
        public string $slug,
    ) {}
}
