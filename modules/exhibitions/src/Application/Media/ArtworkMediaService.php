<?php

namespace Modules\Exhibition\Application\Media;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Artworks\ArtworkService;
use Throwable;

final readonly class ArtworkMediaService
{
    private const EDITABLE = ['draft', 'submitted', 'changes_required', 'rejected'];

    private const ROLES = ['front', 'detail', 'signature', 'back', 'edge', 'scale', 'authenticity_certificate'];

    public function __construct(
        private ArtworkService $artworks,
        private Ve03ArtworkDerivativeService $ve03Derivatives,
    ) {}

    /** @return list<string> */
    public function roles(): array
    {
        return self::ROLES;
    }

    public function ownedMedia(int $coreUserId, string $artworkPublicId, string $mediaPublicId, bool $includeDeleted = false): object
    {
        $artwork = $this->artworks->owned($coreUserId, $artworkPublicId);
        $query = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('public_id', $mediaPublicId);
        if (! $includeDeleted) {
            $query->whereNotIn('state', ['deleted', 'replaced']);
        }

        return $query->first() ?? abort(404);
    }

    public function upload(int $coreUserId, string $artworkPublicId, UploadedFile $file, string $role, ?string $replaces = null): object
    {
        $artwork = $this->artworks->owned($coreUserId, $artworkPublicId);
        $this->assertEditable($artwork);
        $this->assertRole($role);
        $facts = $this->validateImage($file);
        $replaced = $replaces ? $this->ownedMedia($coreUserId, $artworkPublicId, $replaces) : null;

        return $this->storeValidated($artwork, $coreUserId, $file, $role, $facts, $replaced);
    }

    public function uploadForAdministrator(int $actorUserId, string $artworkPublicId, UploadedFile $file, string $role = 'front'): object
    {
        $artwork = DB::table('exhibition_artworks')->where('public_id', $artworkPublicId)->first() ?? abort(404);
        $this->assertEditable($artwork);
        $this->assertRole($role);

        return $this->storeValidated($artwork, $actorUserId, $file, $role, $this->validateImage($file));
    }

    /**
     * Validate the complete selection before writing anything, then roll back the
     * complete batch if processing any image fails.
     *
     * @param  list<UploadedFile>  $files
     * @return list<object>
     */
    public function uploadMany(int $coreUserId, string $artworkPublicId, array $files, string $role): array
    {
        return $this->uploadItems($coreUserId, $artworkPublicId, array_map(
            static fn (UploadedFile $file): array => ['file' => $file, 'role' => $role],
            $files
        ));
    }

    /**
     * @param  list<array{file:UploadedFile,role:string}>  $items
     * @return list<object>
     */
    public function uploadItems(int $coreUserId, string $artworkPublicId, array $items): array
    {
        $artwork = $this->artworks->owned($coreUserId, $artworkPublicId);
        $this->assertEditable($artwork);
        $validated = [];
        foreach ($items as $item) {
            $this->assertRole($item['role']);
            $validated[] = [$item['file'], $item['role'], $this->validateImage($item['file'])];
        }

        $created = [];
        try {
            foreach ($validated as [$file, $role, $facts]) {
                $created[] = $this->storeValidated($artwork, $coreUserId, $file, $role, $facts);
            }
        } catch (Throwable $exception) {
            $this->rollbackUploads($artwork->id, $created);
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The image batch could not be processed. Please retry.', previous: $exception);
        }

        return $created;
    }

    /** @param array{mime:string,size:int,width:int,height:int,checksum:string} $facts */
    private function storeValidated(object $artwork, int $coreUserId, UploadedFile $file, string $role, array $facts, ?object $replaced = null): object
    {
        $publicId = (string) Str::uuid();
        $extension = $facts['mime'] === 'image/png' ? 'png' : ($facts['mime'] === 'image/webp' ? 'webp' : 'jpg');
        $base = 'exhibitions/artworks/'.$artwork->public_id;
        $original = $base.'/originals/'.$publicId.'.'.$extension;
        $display = $base.'/display/'.$publicId.'.jpg';
        $thumbnail = $base.'/thumbnails/'.$publicId.'.jpg';
        $nextOrder = ((int) DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->max('sort_order')) + 1;
        $id = DB::table('exhibition_artwork_media')->insertGetId([
            'public_id' => $publicId, 'artwork_id' => $artwork->id, 'replaces_media_id' => $replaced?->id, 'role' => $role, 'state' => 'processing',
            'is_primary' => false, 'sort_order' => $nextOrder, 'disk' => 'local', 'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $coreUserId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            if (! Storage::disk('local')->put($original, (string) file_get_contents($file->getRealPath()))) {
                throw new DomainException('The private original could not be stored.');
            }
            $this->derivative($file->getRealPath(), $display, 1800, (bool) config('exhibitions.media.watermark_enabled', false));
            $this->derivative($file->getRealPath(), $thumbnail, 480, false);
            DB::transaction(function () use ($id, $facts, $original, $display, $thumbnail, $artwork, $coreUserId, $replaced): void {
                $primaryExists = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('is_primary', true)->where('state', 'ready')->exists();
                DB::table('exhibition_artwork_media')->where('id', $id)->update([
                    'state' => 'ready', 'is_primary' => ! $primaryExists, 'original_path' => $original, 'display_path' => $display, 'thumbnail_path' => $thumbnail,
                    'mime_type' => $facts['mime'], 'size_bytes' => $facts['size'], 'width' => $facts['width'], 'height' => $facts['height'],
                    'checksum_sha256' => $facts['checksum'], 'ready_at' => now(), 'updated_at' => now(),
                ]);
                $new = DB::table('exhibition_artwork_media')->where('id', $id)->first();
                $this->history($new, $coreUserId, 'uploaded');
                if ($replaced) {
                    DB::table('exhibition_artwork_media')->where('id', $replaced->id)->update(['state' => 'replaced', 'is_primary' => false, 'replaced_at' => now(), 'updated_at' => now()]);
                    $this->history(DB::table('exhibition_artwork_media')->where('id', $replaced->id)->first(), $coreUserId, 'replaced', 'Replaced by '.$new->public_id);
                    if ($replaced->is_primary) {
                        DB::table('exhibition_artwork_media')->where('id', $id)->update(['is_primary' => true]);
                    }
                }
            });
            $this->ve03Derivatives->generateForMedia(DB::table('exhibition_artwork_media')->where('id', $id)->first());
        } catch (Throwable $exception) {
            $stored = DB::table('exhibition_artwork_media')->where('id', $id)->first();
            Storage::disk('local')->delete([
                $original,
                $display,
                $thumbnail,
                ...($stored ? $this->ve03Derivatives->paths($stored) : []),
            ]);
            DB::table('exhibition_artwork_media_history')->where('media_public_id', $publicId)->delete();
            DB::table('exhibition_artwork_media')->where('id', $id)->delete();
            throw new DomainException('The image could not be processed. Please retry with a valid image.', previous: $exception);
        }

        return DB::table('exhibition_artwork_media')->where('id', $id)->first();
    }

    /** @param list<object> $created */
    private function rollbackUploads(int $artworkId, array $created): void
    {
        foreach (array_reverse($created) as $media) {
            Storage::disk($media->disk)->delete(array_filter([
                $media->original_path,
                $media->display_path,
                $media->thumbnail_path,
                ...$this->ve03Derivatives->paths($media),
            ]));
            DB::table('exhibition_artwork_media_history')->where('media_public_id', $media->public_id)->delete();
            DB::table('exhibition_artwork_media')->where('id', $media->id)->delete();
        }
        $primaryExists = DB::table('exhibition_artwork_media')->where('artwork_id', $artworkId)->where('state', 'ready')->where('is_primary', true)->exists();
        if (! $primaryExists) {
            $next = DB::table('exhibition_artwork_media')->where('artwork_id', $artworkId)->where('state', 'ready')->orderBy('sort_order')->first();
            if ($next) {
                DB::table('exhibition_artwork_media')->where('id', $next->id)->update(['is_primary' => true, 'updated_at' => now()]);
            }
        }
    }

    /** @param list<string> $orderedPublicIds */
    public function reorder(int $coreUserId, string $artworkPublicId, array $orderedPublicIds): void
    {
        $artwork = $this->artworks->owned($coreUserId, $artworkPublicId);
        $this->assertEditable($artwork);
        $current = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('state', 'ready')->pluck('public_id')->all();
        $expected = $current;
        $actual = $orderedPublicIds;
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new DomainException('The complete owned image set is required for reordering.');
        }
        DB::transaction(function () use ($artwork, $orderedPublicIds, $coreUserId): void {
            foreach ($orderedPublicIds as $index => $id) {
                DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('public_id', $id)->update(['sort_order' => 100000 + $index, 'updated_at' => now()]);
            }
            foreach ($orderedPublicIds as $index => $id) {
                DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('public_id', $id)->update(['sort_order' => $index + 1, 'updated_at' => now()]);
            }
            $this->history(DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('public_id', $orderedPublicIds[0])->first(), $coreUserId, 'reordered', json_encode($orderedPublicIds, JSON_THROW_ON_ERROR));
        });
    }

    public function makePrimary(int $coreUserId, string $artworkPublicId, string $mediaPublicId): void
    {
        $artwork = $this->artworks->owned($coreUserId, $artworkPublicId);
        $this->assertEditable($artwork);
        $media = $this->ownedMedia($coreUserId, $artworkPublicId, $mediaPublicId);
        DB::transaction(function () use ($artwork, $media, $coreUserId): void {
            DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->update(['is_primary' => false]);
            DB::table('exhibition_artwork_media')->where('id', $media->id)->update(['is_primary' => true, 'updated_at' => now()]);
            $this->history(DB::table('exhibition_artwork_media')->where('id', $media->id)->first(), $coreUserId, 'primary_selected');
        });
    }

    public function delete(int $coreUserId, string $artworkPublicId, string $mediaPublicId): void
    {
        $artwork = $this->artworks->owned($coreUserId, $artworkPublicId);
        $this->assertEditable($artwork);
        $media = $this->ownedMedia($coreUserId, $artworkPublicId, $mediaPublicId);
        $this->history($media, $coreUserId, 'deleted', 'Artist removed eligible image.');
        if ($artwork->state === 'draft') {
            Storage::disk($media->disk)->delete(array_filter([
                $media->original_path,
                $media->display_path,
                $media->thumbnail_path,
                ...$this->ve03Derivatives->paths($media),
            ]));
            DB::table('exhibition_artwork_media')->where('id', $media->id)->delete();
        } else {
            DB::table('exhibition_artwork_media')->where('id', $media->id)->update(['state' => 'deleted', 'is_primary' => false, 'deleted_at' => now(), 'updated_at' => now()]);
        }
        if ($media->is_primary) {
            $next = DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->where('state', 'ready')->orderBy('sort_order')->first();
            if ($next) {
                DB::table('exhibition_artwork_media')->where('id', $next->id)->update(['is_primary' => true]);
            }
        }
    }

    /** @return array{mime:string,size:int,width:int,height:int,checksum:string} */
    private function validateImage(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $info = @getimagesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if ($info === false || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new DomainException('Only valid JPEG, PNG, or WebP image bytes are accepted.');
        }
        $extensions = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, $extensions[$mime], true)) {
            throw new DomainException('The filename extension does not match the image signature.');
        }
        [$width,$height] = $info;
        if (in_array($this->exifOrientation($path), [5, 6, 7, 8], true)) {
            [$width, $height] = [$height, $width];
        }
        $min = (int) config('exhibitions.media.minimum_dimension', 400);
        $max = (int) config('exhibitions.media.maximum_dimension', 2500);
        if ($width < $min || $height < $min || $width > $max || $height > $max) {
            throw new DomainException("Artwork images must be between {$min}px and {$max}px on both dimensions.");
        }
        $pixels = (float) $width * (float) $height;
        $maximumMegapixels = (float) config('exhibitions.media.maximum_megapixels', 80);
        if ($maximumMegapixels <= 0 || $pixels > $maximumMegapixels * 1000000) {
            throw new DomainException("Artwork images must not exceed {$maximumMegapixels} megapixels.");
        }
        $estimatedDecodedBytes = ($pixels * 5) + (16 * 1024 * 1024);
        $configuredBudget = max(1, (int) config('exhibitions.media.maximum_decoded_megabytes', 512)) * 1024 * 1024;
        $runtimeLimit = $this->memoryLimitBytes();
        $headroom = max(0, (int) config('exhibitions.media.decode_memory_headroom_megabytes', 32)) * 1024 * 1024;
        // memory_get_usage(true) includes allocator pages PHP keeps reserved after
        // previous requests/tests and can therefore reject a safe image even though
        // that memory is reusable. The non-real usage tracks the bytes that count
        // against the current decode while the explicit headroom remains reserved.
        $runtimeBudget = $runtimeLimit === null ? $configuredBudget : max(0, $runtimeLimit - memory_get_usage(false) - $headroom);
        $decodeBudget = min($configuredBudget, $runtimeBudget);
        if ($estimatedDecodedBytes > $decodeBudget) {
            throw new DomainException('The artwork image would exceed the safe decoded-image memory budget.');
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > ((int) config('exhibitions.media.maximum_kilobytes', 20480) * 1024)) {
            throw new DomainException('The artwork image exceeds the approved file size.');
        }

        return ['mime' => $mime, 'size' => $size, 'width' => $width, 'height' => $height, 'checksum' => hash_file('sha256', $path)];
    }

    private function memoryLimitBytes(): ?float
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return null;
        }$number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return $number * match ($unit) {
            'g' => 1024 * 1024 * 1024,'m' => 1024 * 1024,'k' => 1024,default => 1
        };
    }

    private function derivative(string $source, string $target, int $maximum, bool $watermark): void
    {
        $bytes = (string) file_get_contents($source);
        $image = @imagecreatefromstring($bytes);
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
            if ($canvas === false) {
                throw new DomainException('The optimized image canvas could not be created.');
            }
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            if ($watermark) {
                $color = imagecolorallocatealpha($canvas, 255, 255, 255, 75);
                imagestring($canvas, 5, max(8, $newWidth - 140), max(8, $newHeight - 28), 'Art INPA', $color);
            }
            ob_start();
            $encoded = imagejpeg($canvas, null, 88);
            $output = (string) ob_get_clean();
            if (! $encoded || $output === '' || ! Storage::disk('local')->put($target, $output)) {
                throw new DomainException('The optimized image could not be stored.');
            }
        } finally {
            if ($canvas !== null) {
                imagedestroy($canvas);
            }
            imagedestroy($image);
        }
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
            throw new DomainException('The camera orientation could not be normalized.');
        }
        imagedestroy($image);

        return $rotated;
    }

    private function assertEditable(object $artwork): void
    {
        if (! in_array($artwork->state, self::EDITABLE, true)) {
            throw new DomainException('Images are locked in the current artwork state.');
        }
    }

    private function assertRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new DomainException('Unsupported artwork image role.');
        }
    }

    private function history(object $media, int $actor, string $action, ?string $reason = null): void
    {
        DB::table('exhibition_artwork_media_history')->insert(['public_id' => (string) Str::uuid(), 'artwork_id' => $media->artwork_id, 'media_public_id' => $media->public_id, 'actor_user_id' => $actor, 'action' => $action, 'snapshot' => json_encode((array) $media, JSON_THROW_ON_ERROR), 'reason' => $reason, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
