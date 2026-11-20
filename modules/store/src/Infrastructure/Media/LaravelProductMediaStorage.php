<?php

namespace Modules\Store\Infrastructure\Media;

use Illuminate\Contracts\Filesystem\Factory;
use Modules\Store\Domain\Media\ProductMediaStorage;
use RuntimeException;

final readonly class LaravelProductMediaStorage implements ProductMediaStorage
{
    public function __construct(private Factory $filesystems) {}

    public function put(string $sourcePath, string $targetPath): void
    {
        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The uploaded media stream could not be opened.');
        }
        try {
            if (! $this->filesystems->disk('public')->put($targetPath, $stream)) {
                throw new RuntimeException('The product image could not be stored.');
            }
        } finally {
            fclose($stream);
        }
    }

    public function delete(string $targetPath): void
    {
        $this->filesystems->disk('public')->delete($targetPath);
    }
}
