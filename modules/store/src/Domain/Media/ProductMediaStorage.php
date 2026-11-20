<?php

namespace Modules\Store\Domain\Media;

interface ProductMediaStorage
{
    public function put(string $sourcePath, string $targetPath): void;

    public function delete(string $targetPath): void;
}
