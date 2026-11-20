<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use ZipArchive;

final class Ve03ProcessedBuildStager
{
    /** @var list<string> */
    private const REQUIRED_FILES = [
        'environment.glb',
        'collision.glb',
        'runtime-manifest.json',
        'metadata.json',
        'stats.json',
        'optimization.json',
        'runtime-validation.json',
        'collision-validation.json',
    ];

    /** @var list<string> */
    private const OPTIONAL_FILES = [
        'source-validation.json',
        'license.json',
    ];

    /** @return array<string, mixed> */
    public function stage(UploadedFile $bundle, string $sourceHash, int $actorUserId): array
    {
        $sourceHash = strtolower($sourceHash);
        if (preg_match('/^[a-f0-9]{64}$/', $sourceHash) !== 1) {
            throw new DomainException('The selected GLB source identifier is invalid.');
        }
        if (strtolower($bundle->getClientOriginalExtension()) !== 'zip') {
            throw new DomainException('The processed build must be uploaded as a ZIP file.');
        }

        $disk = Storage::disk('local');
        $uploadBase = 'exhibitions/ve-03/quarantine/uploads/'.$sourceHash;
        $sourcePath = $uploadBase.'/source/source.glb';
        $metadataPath = $uploadBase.'/upload-metadata.json';
        if (! $disk->exists($sourcePath) || ! $disk->exists($metadataPath)) {
            throw new DomainException('The original quarantined GLB source was not found.');
        }

        $temporaryBase = $uploadBase.'/build-staging/'.Str::uuid();
        $zip = new ZipArchive;
        $realPath = $bundle->getRealPath();
        if (! is_string($realPath) || $zip->open($realPath) !== true) {
            throw new DomainException('The processed build ZIP could not be opened.');
        }

        try {
            $this->extractAllowedFiles($zip, $disk, $temporaryBase);
            $summary = $this->validateStagedBuild($disk, $temporaryBase, $sourcePath, $sourceHash);
            $buildBase = $uploadBase.'/builds/'.Str::uuid();
            foreach (array_merge(self::REQUIRED_FILES, self::OPTIONAL_FILES) as $name) {
                $source = $temporaryBase.'/'.$name;
                if ($disk->exists($source) && ! $disk->move($source, $buildBase.'/'.$name)) {
                    throw new DomainException('Unable to place the validated build in private staging.');
                }
            }
            $disk->deleteDirectory($temporaryBase);

            $metadata = $this->json($disk, $metadataPath);
            $metadata = array_replace($metadata, [
                'status' => 'processed_ready_for_import',
                'processed_bundle_name' => $bundle->getClientOriginalName(),
                'processed_by' => $actorUserId,
                'processed_at' => now()->toIso8601String(),
                'staged_build_path' => $buildBase,
                'runtime_sha256' => $summary['runtime_sha256'],
                'collision_sha256' => $summary['collision_sha256'],
                'room_count' => $summary['room_count'],
                'slot_count' => $summary['slot_count'],
            ]);
            $disk->put($metadataPath, json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $metadata;
        } catch (Throwable $exception) {
            $disk->deleteDirectory($temporaryBase);
            throw $exception;
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> */
    public static function requiredFiles(): array
    {
        return self::REQUIRED_FILES;
    }

    private function extractAllowedFiles(ZipArchive $zip, object $disk, string $targetBase): void
    {
        if ($zip->numFiles < count(self::REQUIRED_FILES) || $zip->numFiles > 20) {
            throw new DomainException('The processed build ZIP contains an unexpected number of entries.');
        }

        $allowed = array_flip(array_merge(self::REQUIRED_FILES, self::OPTIONAL_FILES));
        $seen = [];
        $totalBytes = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $rawName = (string) $zip->getNameIndex($index);
            $name = str_replace('\\', '/', $rawName);
            $statistics = $zip->statIndex($index);
            $size = is_array($statistics) ? (int) ($statistics['size'] ?? 0) : 0;

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if (str_contains($name, '/') || str_contains($name, "\0") || ! isset($allowed[$name]) || isset($seen[$name])) {
                throw new DomainException('The processed build ZIP must contain only the documented files at its root.');
            }
            if ((str_ends_with($name, '.json') && $size > 5 * 1024 * 1024)
                || (str_ends_with($name, '.glb') && $size > 100 * 1024 * 1024)) {
                throw new DomainException('A processed build entry exceeds its allowed size.');
            }
            $totalBytes += $size;
            if ($totalBytes > 200 * 1024 * 1024) {
                throw new DomainException('The extracted processed build exceeds 200 MB.');
            }

            $stream = $zip->getStream($rawName);
            if (! is_resource($stream)) {
                throw new DomainException('A processed build entry could not be read.');
            }
            try {
                if (! $disk->put($targetBase.'/'.$name, $stream)) {
                    throw new DomainException('A processed build entry could not be staged.');
                }
            } finally {
                fclose($stream);
            }
            $seen[$name] = true;
        }

        $missing = array_values(array_diff(self::REQUIRED_FILES, array_keys($seen)));
        if ($missing !== []) {
            throw new DomainException('The processed build ZIP is missing: '.implode(', ', $missing).'.');
        }
    }

    /** @return array{runtime_sha256: string, collision_sha256: string, room_count: int, slot_count: int} */
    private function validateStagedBuild(object $disk, string $base, string $sourcePath, string $sourceHash): array
    {
        $manifest = $this->json($disk, $base.'/runtime-manifest.json');
        $metadata = $this->json($disk, $base.'/metadata.json');
        $stats = $this->json($disk, $base.'/stats.json');
        $runtimeValidation = $this->json($disk, $base.'/runtime-validation.json');
        $collisionValidation = $this->json($disk, $base.'/collision-validation.json');

        $sourceBytes = $disk->size($sourcePath);
        if (! hash_equals($sourceHash, strtolower((string) data_get($manifest, 'source.sha256')))
            || (int) data_get($manifest, 'source.bytes') !== $sourceBytes) {
            throw new DomainException('The processed build does not belong to the selected GLB source.');
        }
        if ((int) data_get($runtimeValidation, 'issues.numErrors', 1) !== 0
            || (int) data_get($collisionValidation, 'issues.numErrors', 1) !== 0) {
            throw new DomainException('Khronos validation errors block this processed build.');
        }

        $requiredExtensions = (array) data_get($stats, 'runtime.extensions_required', []);
        foreach (['EXT_meshopt_compression', 'KHR_texture_basisu'] as $extension) {
            if (! in_array($extension, $requiredExtensions, true)) {
                throw new DomainException("Required runtime extension {$extension} is missing.");
            }
        }
        if (count((array) ($metadata['rooms'] ?? [])) < 2
            || count((array) ($metadata['slots'] ?? [])) < 1
            || count((array) ($metadata['spawns'] ?? [])) !== 1
            || (int) data_get($stats, 'collision.triangles', 0) < 1) {
            throw new DomainException('Rooms, slots, spawn, or collision metadata is incomplete.');
        }

        $license = (array) ($manifest['license'] ?? []);
        if (($license['commercial_use_allowed'] ?? false) !== true
            || ($license['runtime_derivative_allowed'] ?? false) !== true
            || empty($license['attribution'])
            || empty($manifest['viewer_compatibility_version'])) {
            throw new DomainException('The processed build does not contain an approved runtime license.');
        }

        $runtimeHash = $this->guardArtifact($disk, $base.'/environment.glb', (array) ($manifest['runtime'] ?? []));
        $collisionHash = $this->guardArtifact($disk, $base.'/collision.glb', (array) ($manifest['collision'] ?? []));
        $this->guardGlb($disk->path($base.'/environment.glb'));
        $this->guardGlb($disk->path($base.'/collision.glb'));

        return [
            'runtime_sha256' => $runtimeHash,
            'collision_sha256' => $collisionHash,
            'room_count' => count($metadata['rooms']),
            'slot_count' => count($metadata['slots']),
        ];
    }

    /** @param array<string, mixed> $expected */
    private function guardArtifact(object $disk, string $path, array $expected): string
    {
        $hash = strtolower((string) ($expected['sha256'] ?? ''));
        $bytes = (int) ($expected['bytes'] ?? 0);
        $actual = $disk->path($path);
        if (! $disk->exists($path) || $bytes < 12 || $disk->size($path) !== $bytes
            || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1
            || ! hash_equals($hash, strtolower((string) hash_file('sha256', $actual)))) {
            throw new DomainException('Processed artifact integrity verification failed for '.basename($path).'.');
        }

        return $hash;
    }

    private function guardGlb(string $path): void
    {
        $header = file_get_contents($path, false, null, 0, 12);
        $data = is_string($header) && strlen($header) === 12 ? unpack('a4magic/Vversion/Vlength', $header) : false;
        if (! is_array($data) || ($data['magic'] ?? null) !== 'glTF' || ($data['version'] ?? null) !== 2
            || (int) ($data['length'] ?? 0) !== filesize($path)) {
            throw new DomainException('A processed artifact is not a structurally valid GLB 2.0 container.');
        }
    }

    /** @return array<string, mixed> */
    private function json(object $disk, string $path): array
    {
        if (! $disk->exists($path)) {
            throw new DomainException('Missing processed build report: '.basename($path));
        }
        try {
            $decoded = json_decode((string) $disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DomainException('Malformed processed build report: '.basename($path), previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new DomainException('Invalid processed build report: '.basename($path));
        }

        return $decoded;
    }
}
