<?php

namespace Modules\Store\Application\Catalog\CreateVariant;

use Modules\Store\Domain\Catalog\VariantStatus;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class CreateVariantCommand
{
    /** @param array<string, string> $options */
    public function __construct(
        public string $productPublicId,
        public string $sku,
        public array $options,
        public ActorContext $actor,
        public VariantStatus $status = VariantStatus::Inactive,
        public bool $isDefault = false,
    ) {}
}
