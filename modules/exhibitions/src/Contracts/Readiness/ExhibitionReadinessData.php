<?php

namespace Modules\Exhibition\Contracts\Readiness;

final readonly class ExhibitionReadinessData
{
    /** @param list<string> $checks */
    public function __construct(
        public bool $available,
        public string $version,
        public bool $artistIdentityTableAvailable,
        public bool $auditTableAvailable,
        public bool $coreIdentityIsExternal,
        public bool $storeGatewayBound,
        public array $checks,
    ) {}
}
