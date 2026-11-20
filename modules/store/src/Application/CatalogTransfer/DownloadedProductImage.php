<?php

namespace Modules\Store\Application\CatalogTransfer;

use Modules\Store\Domain\Media\MediaUploadCandidate;

final class DownloadedProductImage
{
    public function __construct(
        public readonly string $temporaryPath,
        public readonly MediaUploadCandidate $candidate,
        public readonly string $checksum,
    ) {}

    public function cleanup(): void
    {
        if (is_file($this->temporaryPath)) {
            @unlink($this->temporaryPath);
        }
    }
}
