<?php

namespace Modules\Store\Domain\Promotions;

final readonly class PromotionRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $status,
        public int $version,
    ) {}
}
