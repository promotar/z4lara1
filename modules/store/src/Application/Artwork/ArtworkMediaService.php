<?php

namespace Modules\Store\Application\Artwork;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Store\Application\Media\MediaAssetService;
use Modules\Store\Application\Media\ProductMediaService;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Media\MediaSource;
use Modules\Store\Domain\Media\MediaUploadCandidate;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Throwable;

final readonly class ArtworkMediaService
{
    private const ROLES = ['front', 'detail', 'signature', 'back', 'edge', 'scale', 'authenticity_certificate'];

    public function __construct(
        private ArtworkProductService $artworks,
        private MediaAssetService $assets,
        private ProductMediaService $productMedia,
        private PublicIdGenerator $ids,
    ) {}

    /** @return list<string> */
    public function roles(): array
    {
        return self::ROLES;
    }

    public function workspace(int $actorUserId, string $productPublicId, bool $allowAnyOwner = false): array
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);

        return [
            'artwork' => $artwork,
            'media' => DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->orderBy('sort_order')->get(),
            'history' => DB::table('store_artwork_media_history')->where('artwork_detail_id', $artwork->artwork_detail_id)->orderByDesc('occurred_at')->limit(100)->get(),
        ];
    }

    /** @param list<array{file:UploadedFile,role:string}> $items */
    public function uploadItems(int $actorUserId, string $actorName, string $productPublicId, array $items, bool $allowAnyOwner = false): array
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        if ($items === [] || count($items) > 8) {
            throw new DomainException('Upload between one and eight artwork images at a time.');
        }
        $validated = [];
        foreach ($items as $item) {
            if (! in_array($item['role'], self::ROLES, true)) {
                throw new DomainException('Unsupported artwork image role.');
            }
            $validated[] = [$item['file'], $item['role'], $this->validateImage($item['file'])];
        }
        $created = [];
        try {
            foreach ($validated as [$file, $role, $facts]) {
                $created[] = $this->storeValidated($artwork, $actorUserId, $actorName, $file, $role, $facts);
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($created) as $media) {
                $this->removeStored($artwork, $media, false);
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The image batch could not be processed safely.', previous: $exception);
        }

        return $created;
    }

    /**
     * Import one catalog image through the same private-original and public-derivative
     * pipeline used by the artwork editor. This administrative transfer entry point
     * intentionally does not change the artwork workflow state.
     */
    public function importItem(int $actorUserId, string $actorName, string $productPublicId, UploadedFile $file, string $role): object
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new DomainException('Unsupported artwork image role.');
        }

        return $this->storeValidated(
            $this->artwork($actorUserId, $productPublicId, true),
            $actorUserId,
            $actorName,
            $file,
            $role,
            $this->validateImage($file),
        );
    }

    public function replace(int $actorUserId, string $actorName, string $productPublicId, string $mediaPublicId, UploadedFile $file, bool $allowAnyOwner = false): object
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        $old = $this->media($artwork, $mediaPublicId);
        $created = $this->storeValidated($artwork, $actorUserId, $actorName, $file, (string) $old->role, $this->validateImage($file), $old);
        $this->removeStored($artwork, $old, true);

        return $created;
    }

    /** @param list<string> $orderedPublicIds */
    public function reorder(int $actorUserId, string $productPublicId, array $orderedPublicIds, bool $allowAnyOwner = false): void
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        $owned = DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->pluck('public_id')->all();
        $expected = $owned;
        $actual = array_values(array_unique($orderedPublicIds));
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new DomainException('The complete artwork image set is required for reordering.');
        }
        DB::transaction(function () use ($artwork, $orderedPublicIds, $actorUserId): void {
            foreach ($orderedPublicIds as $index => $publicId) {
                DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('public_id', $publicId)->update(['sort_order' => $index + 1, 'updated_at' => now()]);
                $association = DB::table('store_artwork_media')->where('public_id', $publicId)->value('product_media_public_id');
                if ($association) {
                    DB::table('store_product_media')->where('public_id', $association)->update(['position' => $index + 1, 'updated_at' => now()]);
                }
            }
            $first = DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('public_id', $orderedPublicIds[0])->first();
            $this->history($first, $actorUserId, 'reordered', json_encode($orderedPublicIds, JSON_THROW_ON_ERROR));
        });
    }

    public function makePrimary(int $actorUserId, string $productPublicId, string $mediaPublicId, bool $allowAnyOwner = false): void
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        $media = $this->media($artwork, $mediaPublicId);
        if ($media->role !== 'front') {
            throw new DomainException('Only a front artwork image can be selected as primary.');
        }
        DB::transaction(function () use ($artwork, $media, $actorUserId): void {
            $associations = DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->pluck('product_media_public_id')->filter()->all();
            DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->update(['is_primary' => false, 'updated_at' => now()]);
            DB::table('store_product_media')->whereIn('public_id', $associations)->update(['is_primary' => false, 'role' => MediaRole::Gallery->value, 'updated_at' => now()]);
            DB::table('store_artwork_media')->where('id', $media->id)->update(['is_primary' => true, 'updated_at' => now()]);
            DB::table('store_product_media')->where('public_id', $media->product_media_public_id)->update(['is_primary' => true, 'role' => MediaRole::Cover->value, 'updated_at' => now()]);
            $this->history(DB::table('store_artwork_media')->where('id', $media->id)->first(), $actorUserId, 'primary_selected');
        });
    }

    public function delete(int $actorUserId, string $productPublicId, string $mediaPublicId, bool $allowAnyOwner = false): void
    {
        $artwork = $this->artwork($actorUserId, $productPublicId, $allowAnyOwner);
        $this->assertEditable($artwork);
        $media = $this->media($artwork, $mediaPublicId);
        $this->history($media, $actorUserId, 'deleted', 'Artwork image removed from editable draft.');
        $this->removeStored($artwork, $media, false);
        if ($media->is_primary) {
            $front = DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('state', 'ready')->where('role', 'front')->orderBy('sort_order')->first();
            if ($front) {
                $this->makePrimary($actorUserId, $productPublicId, $front->public_id, $allowAnyOwner);
            }
        }
    }

    public function originalFor(int $actorUserId, string $productPublicId, string $mediaPublicId, bool $allowAnyOwner = false): object
    {
        return $this->media($this->artwork($actorUserId, $productPublicId, $allowAnyOwner), $mediaPublicId);
    }

    public function duplicateOwnedMedia(int $actorUserId, string $actorName, string $sourceProductPublicId, string $targetProductPublicId): void
    {
        $source = $this->artworks->findOwned($actorUserId, $sourceProductPublicId);
        $target = $this->artworks->findOwned($actorUserId, $targetProductPublicId);
        $created = [];
        try {
            foreach (DB::table('store_artwork_media')->where('artwork_detail_id', $source->artwork_detail_id)->where('state', 'ready')->orderBy('sort_order')->get() as $media) {
                $temporary = tempnam(sys_get_temp_dir(), 'store-artwork-copy-');
                if ($temporary === false) {
                    throw new DomainException('A private image copy could not be prepared.');
                }
                try {
                    file_put_contents($temporary, Storage::disk($media->original_disk)->get($media->original_path));
                    $upload = new UploadedFile($temporary, $media->original_name, $media->mime_type, null, true);
                    $copy = $this->storeValidated($target, $actorUserId, $actorName, $upload, $media->role, $this->validateImage($upload));
                    $created[] = $copy;
                    if ($media->is_primary) {
                        $this->makePrimary($actorUserId, $targetProductPublicId, $copy->public_id);
                    }
                } finally {
                    @unlink($temporary);
                }
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($created) as $copy) {
                $this->removeStored($target, $copy, false);
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The independent artwork image copies could not be completed safely.', previous: $exception);
        }
    }

    /** @param array{mime:string,size:int,width:int,height:int,checksum:string} $facts */
    private function storeValidated(object $artwork, int $actorUserId, string $actorName, UploadedFile $file, string $role, array $facts, ?object $replaced = null): object
    {
        $publicId = (string) $this->ids->generate();
        $extension = $facts['mime'] === 'image/png' ? 'png' : ($facts['mime'] === 'image/webp' ? 'webp' : 'jpg');
        $base = 'store/artworks/'.$artwork->product_public_id;
        $original = $base.'/originals/'.$publicId.'.'.$extension;
        $display = $base.'/display/'.$publicId.'.jpg';
        $thumbnail = $base.'/thumbnails/'.$publicId.'.jpg';
        $associationPublicId = null;
        $assetPublicId = null;
        try {
            if (! Storage::disk('local')->put($original, (string) file_get_contents($file->getRealPath()))) {
                throw new DomainException('The private original could not be stored.');
            }
            $this->derivative($file->getRealPath(), $display, (int) config('store.artwork_media.display_dimension', 1800), (bool) config('store.artwork_media.watermark_enabled', false));
            $this->derivative($file->getRealPath(), $thumbnail, (int) config('store.artwork_media.thumbnail_dimension', 480), false);
            $displayAbsolute = Storage::disk('public')->path($display);
            $displayFacts = @getimagesize($displayAbsolute);
            $candidate = new MediaUploadCandidate(basename($display), 'image/jpeg', (int) filesize($displayAbsolute), $displayFacts[0] ?? null, $displayFacts[1] ?? null);
            $assetPublicId = $this->assets->register($candidate, 'public', $display, hash_file('sha256', $displayAbsolute), new ActorContext('user', (string) $actorUserId, $actorName));
            $primaryExists = DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('is_primary', true)->where('state', 'ready')->exists();
            $primary = ! $primaryExists && $role === 'front';
            $position = ((int) DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->max('sort_order')) + 1;
            $associationPublicId = $this->productMedia->attach($artwork->product_public_id, null, new MediaSource(assetPublicId: $assetPublicId), $primary ? MediaRole::Cover : MediaRole::Gallery, $primary, $position, 'en', $artwork->title, ucfirst($role));
            DB::table('store_artwork_media')->insert([
                'public_id' => $publicId,
                'artwork_detail_id' => $artwork->artwork_detail_id,
                'replaces_media_id' => $replaced?->id,
                'product_media_public_id' => $associationPublicId,
                'role' => $role,
                'state' => 'ready',
                'is_primary' => $primary || (bool) ($replaced?->is_primary ?? false),
                'sort_order' => $position,
                'original_disk' => 'local',
                'display_disk' => 'public',
                'original_path' => $original,
                'display_path' => $display,
                'thumbnail_path' => $thumbnail,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $facts['mime'],
                'size_bytes' => $facts['size'],
                'width' => $facts['width'],
                'height' => $facts['height'],
                'checksum_sha256' => $facts['checksum'],
                'uploaded_by' => $actorUserId,
                'ready_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $record = DB::table('store_artwork_media')->where('public_id', $publicId)->first();
            $this->history($record, $actorUserId, $replaced ? 'replacement_uploaded' : 'uploaded');
            if ($replaced?->is_primary) {
                $this->makePrimary($actorUserId, $artwork->product_public_id, $publicId, (int) $artwork->owner_user_id !== $actorUserId);
            }

            return DB::table('store_artwork_media')->where('public_id', $publicId)->first();
        } catch (Throwable $exception) {
            if ($associationPublicId) {
                try {
                    $this->productMedia->remove($artwork->product_public_id, $associationPublicId);
                } catch (Throwable) {
                }
            }
            if ($assetPublicId) {
                DB::table('store_media_assets')->where('public_id', $assetPublicId)->delete();
            }
            Storage::disk('local')->delete($original);
            Storage::disk('public')->delete([$display, $thumbnail]);
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The artwork image could not be processed safely.', previous: $exception);
        }
    }

    private function removeStored(object $artwork, object $media, bool $replaced): void
    {
        if ($media->product_media_public_id) {
            $assetId = DB::table('store_product_media')->where('public_id', $media->product_media_public_id)->value('media_asset_id');
            try {
                $this->productMedia->remove($artwork->product_public_id, $media->product_media_public_id);
            } catch (Throwable) {
            }
            if ($assetId && ! DB::table('store_product_media')->where('media_asset_id', $assetId)->exists()) {
                DB::table('store_media_assets')->where('id', $assetId)->delete();
            }
        }
        Storage::disk($media->original_disk)->delete($media->original_path);
        Storage::disk($media->display_disk)->delete([$media->display_path, $media->thumbnail_path]);
        if ($replaced) {
            DB::table('store_artwork_media')->where('id', $media->id)->update(['state' => 'replaced', 'is_primary' => false, 'replaced_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('store_artwork_media')->where('id', $media->id)->delete();
        }
    }

    /** @return array{mime:string,size:int,width:int,height:int,checksum:string} */
    private function validateImage(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $info = @getimagesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if ($info === false || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new DomainException('Only valid JPEG, PNG, or WebP artwork images are accepted.');
        }
        $extensions = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions[$mime], true)) {
            throw new DomainException('The image filename extension does not match its bytes.');
        }
        [$width, $height] = $info;
        if (in_array($this->exifOrientation($path), [5, 6, 7, 8], true)) {
            [$width, $height] = [$height, $width];
        }
        $minimum = (int) config('store.artwork_media.minimum_dimension', 800);
        $maximum = (int) config('store.artwork_media.maximum_dimension', 2500);
        if ($width < $minimum || $height < $minimum || $width > $maximum || $height > $maximum) {
            throw new DomainException("Artwork images must be between {$minimum}px and {$maximum}px on both dimensions.");
        }
        if ((float) $width * (float) $height > (float) config('store.artwork_media.maximum_megapixels', 80) * 1_000_000) {
            throw new DomainException('The artwork image exceeds the safe megapixel limit.');
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (int) config('store.artwork_media.maximum_upload_bytes', 20_971_520)) {
            throw new DomainException('The artwork image exceeds the approved file size.');
        }

        return ['mime' => $mime, 'size' => $size, 'width' => $width, 'height' => $height, 'checksum' => hash_file('sha256', $path)];
    }

    private function derivative(string $source, string $target, int $maximum, bool $watermark): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            throw new DomainException('Corrupted image data.');
        }
        $canvas = null;
        try {
            $image = $this->applyExifOrientation($image, $source);
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, $maximum / max($width, $height));
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            if ($watermark) {
                imagestring($canvas, 5, max(8, $newWidth - 140), max(8, $newHeight - 28), 'Art INPA', imagecolorallocatealpha($canvas, 255, 255, 255, 75));
            }
            ob_start();
            $encoded = imagejpeg($canvas, null, 88);
            $output = (string) ob_get_clean();
            if (! $encoded || $output === '') {
                throw new DomainException('The optimized artwork image could not be stored.');
            }
            $this->writeVerifiedPublicDerivative($target, $output);
        } finally {
            if ($canvas !== null) {
                imagedestroy($canvas);
            }
            imagedestroy($image);
        }
    }

    private function writeVerifiedPublicDerivative(string $target, string $output): void
    {
        $disk = Storage::disk('public');
        $writeFailure = null;

        try {
            if ($disk->put($target, $output)) {
                return;
            }
        } catch (Throwable $exception) {
            $writeFailure = $exception;
        }

        try {
            $expectedSize = strlen($output);
            $storedChecksum = $disk->exists($target) && $disk->size($target) === $expectedSize
                ? hash_file('sha256', $disk->path($target))
                : false;

            if (is_string($storedChecksum) && hash_equals(hash('sha256', $output), $storedChecksum)) {
                return;
            }
        } catch (Throwable $exception) {
            $writeFailure ??= $exception;
        }

        try {
            $disk->delete($target);
        } catch (Throwable) {
        }

        throw new DomainException('The optimized artwork image could not be stored.', previous: $writeFailure);
    }

    private function applyExifOrientation(mixed $image, string $source): mixed
    {
        $orientation = $this->exifOrientation($source);
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 3) {
            $image = $this->rotate($image, 180);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        } elseif ($orientation === 5) {
            $image = $this->rotate($image, -90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 6) {
            $image = $this->rotate($image, -90);
        } elseif ($orientation === 7) {
            $image = $this->rotate($image, 90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 8) {
            $image = $this->rotate($image, 90);
        }

        return $image;
    }

    private function exifOrientation(string $source): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($source, 'IFD0', true) ?: [];

        return (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1);
    }

    private function rotate(mixed $image, int $degrees): mixed
    {
        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            throw new DomainException('Image orientation could not be normalized.');
        }
        imagedestroy($image);

        return $rotated;
    }

    private function artwork(int $actorUserId, string $productPublicId, bool $allowAnyOwner): object
    {
        return $allowAnyOwner ? $this->artworks->find($productPublicId) : $this->artworks->findOwned($actorUserId, $productPublicId);
    }

    private function media(object $artwork, string $mediaPublicId): object
    {
        return DB::table('store_artwork_media')->where('artwork_detail_id', $artwork->artwork_detail_id)->where('public_id', $mediaPublicId)->where('state', 'ready')->first() ?? abort(404);
    }

    private function assertEditable(object $artwork): void
    {
        if (! in_array($artwork->workflow_state, ['draft', 'changes_required', 'rejected'], true)) {
            throw new DomainException('Artwork images are locked in the current workflow state.');
        }
    }

    private function history(object $media, int $actorUserId, string $action, ?string $reason = null): void
    {
        DB::table('store_artwork_media_history')->insert([
            'public_id' => (string) $this->ids->generate(),
            'artwork_detail_id' => $media->artwork_detail_id,
            'media_public_id' => $media->public_id,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'snapshot' => json_encode((array) $media, JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
