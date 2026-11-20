<?php

namespace Modules\Exhibition\Application\Commission;

final readonly class CommissionResolution
{
    public function __construct(
        public int $basisPoints,
        public string $rate,
        public string $source,
    ) {}
}
