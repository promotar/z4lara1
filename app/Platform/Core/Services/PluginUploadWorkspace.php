<?php

namespace App\Platform\Core\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

final class PluginUploadWorkspace
{
    private const DISK = 'plugin_uploads';

    public function __construct(
        FilesystemManager $filesystems,
    ) {
        $this->disk = $filesystems->disk(self::DISK);
    }

    private readonly FilesystemAdapter $disk;

    public function store(UploadedFile $archive): string
    {
        try {
            $path = $archive->store('tmp', self::DISK);
        } catch (Throwable $exception) {
            throw $this->unavailable($exception);
        }

        if (! is_string($path) || $path === '') {
            throw $this->unavailable();
        }

        return $path;
    }

    public function createExtractionDirectory(string $prefix): string
    {
        $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '-', $prefix) ?: 'plugin';
        $path = 'extracted/'.$safePrefix.'-'.bin2hex(random_bytes(12));

        try {
            if (! $this->disk->makeDirectory($path)) {
                throw $this->unavailable();
            }
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw $this->unavailable($exception);
        }

        @chmod($this->absolutePath($path), 0770);

        return $path;
    }

    public function preserveForUpdate(string $temporaryPath, string $token): string
    {
        $source = $this->normalize($temporaryPath);
        $destination = $this->normalize('pending_updates/'.$token.'.zip');

        try {
            if (! $this->disk->copy($source, $destination)) {
                throw $this->unavailable();
            }
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw $this->unavailable($exception);
        }

        return $destination;
    }

    public function absolutePath(string $path): string
    {
        return $this->disk->path($this->normalize($path));
    }

    public function exists(string $path): bool
    {
        try {
            return $this->disk->exists($this->normalize($path));
        } catch (Throwable) {
            return false;
        }
    }

    public function discardFile(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        try {
            $this->disk->delete($this->normalize($path));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function discardDirectory(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        try {
            $this->disk->deleteDirectory($this->normalize($path));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function normalize(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');

        if (
            $normalized === ''
            || str_contains($normalized, "\0")
            || preg_match('~(^|/)\.\.(/|$)~', $normalized)
            || preg_match('/^[A-Za-z]:\//', $normalized)
        ) {
            throw new RuntimeException('The plugin upload workspace received an invalid internal path.');
        }

        return $normalized;
    }

    private function unavailable(?Throwable $previous = null): RuntimeException
    {
        return new RuntimeException(
            'The private plugin upload workspace is not writable. Verify storage/app/plugin_uploads permissions for the PHP runtime user and try again.',
            0,
            $previous,
        );
    }
}
