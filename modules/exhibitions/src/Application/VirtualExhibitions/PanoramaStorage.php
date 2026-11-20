<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PanoramaStorage
{
    /** @return array{disk:string,path:string,mime:string,size:int,width:int,height:int,checksum:string} */
    public function store(string $roomPublicId, UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new DomainException('The panorama upload did not complete successfully.');
        }

        $maximumKilobytes = (int) config('exhibitions.virtual_exhibitions.panorama.maximum_kilobytes', 25600);
        if ($file->getSize() <= 0 || $file->getSize() > $maximumKilobytes * 1024) {
            throw new DomainException("The panorama must not exceed {$maximumKilobytes} KB.");
        }

        $path = $file->getRealPath();
        $image = $path ? @getimagesize($path) : false;
        if ($image === false) {
            throw new DomainException('The panorama is corrupt or is not a supported raster image.');
        }

        [$width, $height] = $image;
        $mime = (string) ($image['mime'] ?? '');
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! isset($allowed[$mime]) || ! in_array($file->getMimeType(), array_keys($allowed), true)) {
            throw new DomainException('The panorama must be a genuine JPEG, PNG, or WebP image.');
        }

        $minimumWidth = (int) config('exhibitions.virtual_exhibitions.panorama.minimum_width', 2048);
        $maximumMegapixels = (int) config('exhibitions.virtual_exhibitions.panorama.maximum_megapixels', 120);
        $ratioTolerance = (float) config('exhibitions.virtual_exhibitions.panorama.ratio_tolerance', 0.03);
        if ($width < $minimumWidth || $height < (int) ($minimumWidth / 2)) {
            throw new DomainException("The panorama must be at least {$minimumWidth}×".(int) ($minimumWidth / 2).' pixels.');
        }
        if (($width * $height) > $maximumMegapixels * 1_000_000) {
            throw new DomainException("The panorama exceeds the {$maximumMegapixels}-megapixel safety limit.");
        }
        if (abs(($width / $height) - 2.0) > $ratioTolerance) {
            throw new DomainException('The panorama must use an equirectangular 2:1 aspect ratio.');
        }

        $disk = (string) config('exhibitions.virtual_exhibitions.panorama.disk', 'local');
        $storagePath = 'exhibitions/virtual/rooms/'.$roomPublicId.'/panorama/'.Str::uuid().'.'.$allowed[$mime];
        $stream = fopen($path, 'rb');
        if ($stream === false || ! Storage::disk($disk)->put($storagePath, $stream, ['visibility' => 'private'])) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new DomainException('The panorama could not be stored. Please retry.');
        }
        if (is_resource($stream)) {
            fclose($stream);
        }

        return [
            'disk' => $disk,
            'path' => $storagePath,
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'width' => (int) $width,
            'height' => (int) $height,
            'checksum' => hash_file('sha256', $path),
        ];
    }
}
