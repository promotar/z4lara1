<?php

namespace Modules\Store\Domain\Media;

final readonly class MediaUploadCandidate
{
    public function __construct(
        public string $originalFilename,
        public string $detectedMimeType,
        public int $sizeBytes,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
