<?php

namespace Modules\Store\Application\Catalog;

use Modules\Store\Domain\Media\MediaUploadCandidate;

final readonly class ProductEditorImage
{
    public function __construct(
        public string $temporaryPath,
        public MediaUploadCandidate $candidate,
    ) {}
}
