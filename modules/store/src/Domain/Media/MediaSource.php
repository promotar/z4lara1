<?php

namespace Modules\Store\Domain\Media;

use InvalidArgumentException;
use Modules\Store\Domain\Shared\PublicId;

final readonly class MediaSource
{
    public function __construct(
        public ?string $assetPublicId = null,
        public ?string $externalSource = null,
        public ?string $externalKey = null,
    ) {
        $internal = $assetPublicId !== null;
        $external = $externalSource !== null || $externalKey !== null;

        if ($internal === $external || ($external && ($externalSource === null || $externalKey === null))) {
            throw new InvalidArgumentException('Media association requires exactly one complete internal or external source.');
        }

        if ($assetPublicId !== null) {
            new PublicId($assetPublicId);
        }

        if ($externalSource !== null && ! preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $externalSource)) {
            throw new InvalidArgumentException('External media source code is invalid.');
        }
    }
}
