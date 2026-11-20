<?php

namespace Modules\Exhibition\Application\Media;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class Ve03ArtworkDerivativeService
{
    public const PROFILE = 've03-srgb-jpeg-v1';

    /** @return array{room:string,near:string,popup:string} */
    public function generateForMedia(object $media): array
    {
        if (($media->state ?? null) !== 'ready' || empty($media->public_id) || empty($media->disk)) {
            throw new DomainException('Only ready artwork media can receive VE-03 derivatives.');
        }

        $disk = Storage::disk((string) $media->disk);
        $sourcePath = $this->sourcePath($media);
        if (! $sourcePath || ! $disk->exists($sourcePath)) {
            throw new DomainException('The approved artwork source derivative is unavailable.');
        }

        $source = $disk->path($sourcePath);
        $base = 'exhibitions/ve-03/artworks/'.(string) $media->public_id;
        $paths = [
            'room' => $base.'/room.jpg',
            'near' => $base.'/near.jpg',
            'popup' => $base.'/popup.jpg',
        ];
        $limits = [
            'room' => (int) config('exhibitions.virtual_exhibitions.ve03.artwork_derivatives.room_long_edge', 1024),
            'near' => (int) config('exhibitions.virtual_exhibitions.ve03.artwork_derivatives.near_long_edge', 2048),
            'popup' => (int) config('exhibitions.virtual_exhibitions.ve03.artwork_derivatives.popup_long_edge', 2560),
        ];

        $written = [];
        try {
            foreach ($paths as $variant => $target) {
                $this->writeSrgbJpeg($source, $disk->path($target), $limits[$variant]);
                $written[] = $target;
            }
            DB::table('exhibition_artwork_media')->where('id', $media->id)->update([
                've03_room_path' => $paths['room'],
                've03_near_path' => $paths['near'],
                've03_popup_path' => $paths['popup'],
                've03_derivative_profile' => self::PROFILE,
                've03_derivative_source_sha256' => hash_file('sha256', $source),
                've03_derivatives_ready_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $disk->delete($written);
            throw $exception instanceof DomainException
                ? $exception
                : new DomainException('VE-03 artwork derivatives could not be generated.', previous: $exception);
        }

        return $paths;
    }

    /** @return array{path:string,hash:string,size:int,disk:string} */
    public function publicArtifact(object $media, string $variant): array
    {
        $column = match ($variant) {
            'room' => 've03_room_path',
            'near' => 've03_near_path',
            'popup' => 've03_popup_path',
            default => throw new DomainException('Invalid VE-03 artwork derivative variant.'),
        };
        $path = (string) ($media->{$column} ?? '');
        if ($path === '') {
            $path = (string) ($media->display_path ?? '');
        }
        $diskName = (string) ($media->disk ?? 'local');
        $disk = Storage::disk($diskName);
        if ($path === '' || ! $disk->exists($path)) {
            throw new DomainException('The public artwork derivative is unavailable.');
        }
        $absolute = $disk->path($path);

        return [
            'path' => $absolute,
            'hash' => hash_file('sha256', $absolute),
            'size' => filesize($absolute),
            'disk' => $diskName,
        ];
    }

    /** @return list<string> */
    public function paths(object $media): array
    {
        return array_values(array_filter([
            $media->ve03_room_path ?? null,
            $media->ve03_near_path ?? null,
            $media->ve03_popup_path ?? null,
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    private function sourcePath(object $media): ?string
    {
        foreach ([$media->original_path ?? null, $media->display_path ?? null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function writeSrgbJpeg(string $source, string $target, int $maximum): void
    {
        if ($maximum < 1) {
            throw new DomainException('The VE-03 derivative size is invalid.');
        }
        $bytes = @file_get_contents($source);
        $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($image === false) {
            throw new DomainException('The approved artwork image could not be decoded.');
        }
        $canvas = null;
        try {
            $image = $this->applyExifOrientation($image, $source);
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, $maximum / max($width, $height));
            $nextWidth = max(1, (int) round($width * $scale));
            $nextHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($nextWidth, $nextHeight);
            if ($canvas === false) {
                throw new DomainException('The VE-03 derivative canvas could not be created.');
            }
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height);
            imageinterlace($canvas, true);
            $directory = dirname($target);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new DomainException('The VE-03 derivative directory could not be created.');
            }
            if (! imagejpeg($canvas, $target, 92)) {
                throw new DomainException('The VE-03 derivative could not be encoded.');
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
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($source, 'IFD0', true) ?: [];
        $orientation = (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1);
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 3) {
            $image = $this->rotate($image, 180);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        if ($orientation === 5) {
            $image = $this->rotate($image, -90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 6) {
            $image = $this->rotate($image, -90);
        }
        if ($orientation === 7) {
            $image = $this->rotate($image, 90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 8) {
            $image = $this->rotate($image, 90);
        }

        return $image;
    }

    private function rotate(mixed $image, int $degrees): mixed
    {
        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            throw new DomainException('The artwork orientation could not be normalized.');
        }
        imagedestroy($image);

        return $rotated;
    }
}
