<?php

namespace Modules\Store\Application\Catalog;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

final readonly class CategoryImageService
{
    public function __construct(
        private ConnectionInterface $database,
        private FilesystemFactory $filesystems,
    ) {}

    public function update(string $categoryPublicId, ?UploadedFile $image, bool $remove): void
    {
        $record = $this->database->table('store_categories')->where('public_id', $categoryPublicId)->first();
        if ($record === null) {
            throw new RuntimeException('Category not found.');
        }
        if ($image === null && ! $remove) {
            throw new RuntimeException('Choose a category image or request removal.');
        }

        $newPath = null;
        try {
            if ($image !== null) {
                $newPath = $image->storePublicly("store/categories/{$categoryPublicId}", 'public');
            }
            $this->database->table('store_categories')->where('id', $record->id)->update([
                'image_path' => $newPath,
                'updated_at' => now(),
            ]);
            if ($record->image_path !== null) {
                $this->filesystems->disk('public')->delete($record->image_path);
            }
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->filesystems->disk('public')->delete($newPath);
            }
            throw $exception;
        }
    }
}
