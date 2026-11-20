<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;

/**
 * Validated physical dimensions for VE-03 artwork placement.
 *
 * A missing value must never be replaced with an arbitrary display size. The
 * real dimensions are part of collision and wall-clearance calculations, so a
 * placement is unsafe until the artist or administrator supplies them.
 */
final readonly class Ve03ArtworkDimensions
{
    public const MISSING_MESSAGE = 'Artwork dimensions are missing. Add a valid width and height before placing it in a 3D exhibition.';

    private const UNIT_FACTORS = [
        'mm' => 0.001,
        'cm' => 0.01,
        'm' => 1.0,
        'in' => 0.0254,
    ];

    private function __construct(
        public float $widthMeters,
        public float $heightMeters,
    ) {}

    public static function fromArtwork(object $artwork): self
    {
        return self::tryFromArtwork($artwork)
            ?? throw new DomainException(self::MISSING_MESSAGE);
    }

    public static function tryFromArtwork(object $artwork): ?self
    {
        $unit = strtolower(trim((string) ($artwork->dimension_unit ?? '')));
        $factor = self::UNIT_FACTORS[$unit] ?? null;
        $width = $artwork->width ?? null;
        $height = $artwork->height ?? null;

        if ($factor === null
            || ! is_numeric($width)
            || ! is_numeric($height)
            || (float) $width <= 0
            || (float) $height <= 0) {
            return null;
        }

        $widthMeters = round((float) $width * $factor, 4);
        $heightMeters = round((float) $height * $factor, 4);
        if ($widthMeters <= 0 || $heightMeters <= 0) {
            return null;
        }

        return new self($widthMeters, $heightMeters);
    }

    public static function isValid(object $artwork): bool
    {
        return self::tryFromArtwork($artwork) !== null;
    }
}
