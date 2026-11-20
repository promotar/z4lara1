<?php

namespace Modules\Store\Application\Promotions;

final readonly class PromotionTargetInput
{
    public function __construct(
        public string $type,
        public ?string $publicId = null,
        public bool $includeDescendants = false,
    ) {}
}
