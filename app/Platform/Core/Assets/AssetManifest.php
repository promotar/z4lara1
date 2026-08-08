<?php

namespace App\Platform\Core\Assets;

class AssetManifest
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $type,
        public readonly string $slug,
        public readonly string $sourcePath,
        public readonly string $destinationPath,
        public readonly array $manifest = [],
    ) {
        //
    }
}
