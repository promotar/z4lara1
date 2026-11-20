<?php

namespace Modules\Store\Application\Catalog;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use RuntimeException;
use Throwable;

final readonly class BrandCatalogService
{
    public function __construct(
        private ConnectionInterface $database,
        private FilesystemFactory $filesystems,
        private PublicIdGenerator $ids,
        private Clock $clock,
    ) {}

    /** @param array{name:string,slug:string,description?:string|null,status?:string,position?:int|string|null} $data */
    public function create(array $data, ?UploadedFile $logo, ActorContext $actor): string
    {
        $publicId = (string) $this->ids->generate();
        $logoPath = null;

        try {
            if ($logo !== null) {
                $logoPath = $logo->storePublicly("store/brands/{$publicId}", 'public');
            }
            $now = $this->clock->now();
            $this->database->table('store_brands')->insert([
                'public_id' => $publicId,
                'name' => trim($data['name']),
                'slug' => strtolower(trim($data['slug'])),
                'description' => $data['description'] ?? null,
                'logo_path' => $logoPath,
                'status' => $data['status'] ?? 'active',
                'position' => (int) ($data['position'] ?? 0),
                'version' => 1,
                'created_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $exception) {
            if ($logoPath !== null) {
                $this->filesystems->disk('public')->delete($logoPath);
            }
            throw $exception;
        }

        return $publicId;
    }

    /** @param array{name:string,slug:string,description?:string|null,status:string,position:int|string,version:int|string} $data */
    public function update(
        string $publicId,
        array $data,
        ?UploadedFile $logo,
        bool $removeLogo,
        ActorContext $actor,
    ): void {
        $record = $this->database->table('store_brands')->where('public_id', $publicId)->first();
        if ($record === null) {
            throw new RuntimeException('Brand not found.');
        }

        $newLogoPath = null;
        try {
            if ($logo !== null) {
                $newLogoPath = $logo->storePublicly("store/brands/{$publicId}", 'public');
            }
            $now = $this->clock->now();
            $updated = $this->database->table('store_brands')
                ->where('id', $record->id)
                ->where('version', (int) $data['version'])
                ->update([
                    'name' => trim($data['name']),
                    'slug' => strtolower(trim($data['slug'])),
                    'description' => $data['description'] ?? null,
                    'logo_path' => $newLogoPath ?? ($removeLogo ? null : $record->logo_path),
                    'status' => $data['status'],
                    'position' => (int) $data['position'],
                    'version' => $this->database->raw('version + 1'),
                    'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                    'updated_at' => $now,
                    'archived_at' => $data['status'] === 'archived' ? $now : null,
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('Brand version is stale. Reload the page and try again.');
            }
            if (($newLogoPath !== null || $removeLogo) && $record->logo_path !== null) {
                $this->filesystems->disk('public')->delete($record->logo_path);
            }
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                $this->filesystems->disk('public')->delete($newLogoPath);
            }
            throw $exception;
        }
    }

    /**
     * Retire a brand without deleting any product record. Linked products become unbranded,
     * which keeps them visible and editable while making the unlinking explicit.
     */
    public function archive(string $publicId, int $expectedVersion, ActorContext $actor): void
    {
        $this->database->transaction(function () use ($publicId, $expectedVersion, $actor): void {
            $record = $this->database->table('store_brands')->where('public_id', $publicId)->lockForUpdate()->first();
            if ($record === null) {
                throw new RuntimeException('Brand not found.');
            }
            if ((int) $record->version !== $expectedVersion) {
                throw new RuntimeException('Brand version is stale. Reload the page and try again.');
            }

            $now = $this->clock->now();
            $this->database->table('store_brands')->where('id', $record->id)->update([
                'status' => 'archived',
                'archived_at' => $now,
                'version' => $this->database->raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]);
            $this->database->table('store_products')->where('brand_id', $record->id)->update([
                'brand_id' => null,
                'version' => $this->database->raw('version + 1'),
                'updated_by' => $actor->type === 'user' ? (int) $actor->id : null,
                'updated_at' => $now,
            ]);
        });
    }
}
