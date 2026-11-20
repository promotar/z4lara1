<?php

namespace Modules\Store\Domain\Media;

use InvalidArgumentException;

final readonly class MediaUploadValidationService
{
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    public function __construct(
        private int $maximumBytes = 15_728_640,
        private int $maximumDimension = 12_000,
    ) {}

    public function validate(MediaUploadCandidate $candidate): void
    {
        if ($candidate->sizeBytes <= 0 || $candidate->sizeBytes > $this->maximumBytes) {
            throw new InvalidArgumentException('Media upload size is outside the allowed range.');
        }

        $extensions = self::EXTENSIONS_BY_MIME[$candidate->detectedMimeType] ?? null;
        if ($extensions === null) {
            throw new InvalidArgumentException('Server-detected media MIME type is not allowed.');
        }

        $extension = strtolower(pathinfo($candidate->originalFilename, PATHINFO_EXTENSION));
        if (! in_array($extension, $extensions, true)) {
            throw new InvalidArgumentException('Media filename extension does not match the server-detected MIME type.');
        }

        if (($candidate->width !== null && ($candidate->width <= 0 || $candidate->width > $this->maximumDimension))
            || ($candidate->height !== null && ($candidate->height <= 0 || $candidate->height > $this->maximumDimension))) {
            throw new InvalidArgumentException('Media dimensions are outside the allowed range.');
        }
    }
}
