<?php

namespace Modules\Exhibition\Application\Security;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class PrivateEvidenceUpload
{
    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{bytes:string,mime_type:string,extension:string,original_name:string,size_bytes:int,checksum_sha256:string}
     */
    public static function inspect(UploadedFile $file, int $maximumBytes = 20 * 1024 * 1024): array
    {
        $size = $file->getSize();
        $path = $file->getRealPath();

        if (! $file->isValid() || ! is_int($size) || $size < 1 || $size > $maximumBytes || ! is_string($path) || ! is_readable($path)) {
            throw new DomainException('Evidence file is invalid or too large.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime) || ! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new DomainException('Evidence must be a PDF, JPEG, PNG, or WebP file.');
        }

        $bytes = file_get_contents($path);
        if (! is_string($bytes) || strlen($bytes) !== $size || ! self::hasExpectedSignature($mime, $bytes)) {
            throw new DomainException('Evidence content does not match an allowed file type.');
        }

        $extension = self::MIME_EXTENSIONS[$mime];

        return [
            'bytes' => $bytes,
            'mime_type' => $mime,
            'extension' => $extension,
            'original_name' => self::safeOriginalName($file->getClientOriginalName(), $extension),
            'size_bytes' => $size,
            'checksum_sha256' => hash('sha256', $bytes),
        ];
    }

    private static function hasExpectedSignature(string $mime, string $bytes): bool
    {
        return match ($mime) {
            'application/pdf' => str_starts_with($bytes, '%PDF-'),
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1A\n"),
            'image/webp' => strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP',
            default => false,
        };
    }

    private static function safeOriginalName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $stem = preg_replace('/[^\pL\pN._ -]+/u', '_', $stem) ?: 'evidence';
        $stem = trim($stem, ". \t\n\r\0\x0B");
        $stem = Str::limit($stem !== '' ? $stem : 'evidence', 180, '');

        return $stem.'.'.$extension;
    }
}
