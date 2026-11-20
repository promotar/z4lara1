<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

final readonly class Ve03AssetVersionImporter
{
    public function __construct(
        private Filesystem $files,
        private Ve03VersionComparator $comparator,
        private Ve03LuxuryHallSlotLayout $slotLayout,
    ) {}

    /** @return array<string, mixed> */
    public function import(string $sourcePath, string $buildDirectory, int $actorUserId, bool $publish = false): array
    {
        $sourcePath = $this->trustedSource($sourcePath);
        $buildDirectory = $this->existingDirectory($buildDirectory);
        $manifest = $this->json($buildDirectory.'/runtime-manifest.json');
        $metadata = $this->json($buildDirectory.'/metadata.json');
        $metadata = $this->slotLayout->normalizeMetadata($metadata);
        $stats = $this->json($buildDirectory.'/stats.json');
        $optimization = $this->json($buildDirectory.'/optimization.json');
        $runtimeValidation = $this->json($buildDirectory.'/runtime-validation.json');
        $collisionValidation = $this->json($buildDirectory.'/collision-validation.json');
        $uploadMetadataPath = dirname(dirname($sourcePath)).'/upload-metadata.json';
        $uploadMetadata = is_file($uploadMetadataPath) ? $this->json($uploadMetadataPath) : [];
        $sourceHash = strtolower((string) data_get($manifest, 'source.sha256'));
        $replacementEnvironmentId = (int) ($uploadMetadata['replacement_environment_id'] ?? 0);
        $replacementVersionPublicId = trim((string) ($uploadMetadata['replacement_version_public_id'] ?? ''));
        $hallMode = $replacementEnvironmentId > 0 ? 'replacement' : 'new';
        $environmentCode = $this->environmentCode($uploadMetadata, $manifest, $sourceHash);
        $environmentTitle = $this->environmentTitle($uploadMetadata, $manifest, $sourceHash);
        $metadata['_hall'] = [
            'mode' => $hallMode,
            'environment_code' => $environmentCode,
            'replacement_version_public_id' => $replacementVersionPublicId !== '' ? $replacementVersionPublicId : null,
        ];
        $license = $this->normalizedLicense(
            is_array($manifest['license'] ?? null) ? $manifest['license'] : [],
            $uploadMetadata,
            $manifest,
        );

        $this->guardReports($manifest, $metadata, $stats, $runtimeValidation, $collisionValidation, $license);

        $runtimeFile = $buildDirectory.'/environment.glb';
        $collisionFile = $buildDirectory.'/collision.glb';
        $this->guardArtifact($runtimeFile, (string) data_get($manifest, 'runtime.sha256'), (int) data_get($manifest, 'runtime.bytes'));
        $this->guardArtifact($collisionFile, (string) data_get($manifest, 'collision.sha256'), (int) data_get($manifest, 'collision.bytes'));
        $this->guardArtifact($sourcePath, (string) data_get($manifest, 'source.sha256'), (int) data_get($manifest, 'source.bytes'));

        $runtimeHash = strtolower((string) data_get($manifest, 'runtime.sha256'));
        $immutableRelative = 'exhibitions/ve-03/runtime/'.$runtimeHash;
        $immutableDirectory = storage_path('app/private/'.$immutableRelative);
        $this->copyImmutable($buildDirectory, $immutableDirectory);

        $version = DB::transaction(function () use (
            $manifest,
            $metadata,
            $stats,
            $optimization,
            $runtimeValidation,
            $collisionValidation,
            $license,
            $sourcePath,
            $actorUserId,
            $runtimeHash,
            $immutableRelative,
            $uploadMetadata,
            $replacementEnvironmentId,
            $replacementVersionPublicId,
            $environmentCode,
            $environmentTitle,
        ): array {
            if ($replacementEnvironmentId > 0) {
                $environment = DB::table('exhibition_virtual_environments')
                    ->where('id', $replacementEnvironmentId)
                    ->lockForUpdate()
                    ->first();
                if (! $environment) {
                    throw new DomainException('The hall selected for replacement no longer exists.');
                }
                if ($replacementVersionPublicId === '' || ! DB::table('exhibition_virtual_environment_versions')
                    ->where('public_id', $replacementVersionPublicId)
                    ->where('environment_id', $environment->id)
                    ->exists()) {
                    throw new DomainException('The replacement target does not belong to the selected hall.');
                }
            } else {
                $environment = DB::table('exhibition_virtual_environments')
                    ->where('code', $environmentCode)
                    ->lockForUpdate()
                    ->first();
            }
            if (! $environment) {
                $environmentId = DB::table('exhibition_virtual_environments')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'code' => $environmentCode,
                    'title' => $environmentTitle,
                    'status' => 'draft',
                    'active_version_id' => null,
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $environmentId = (int) $environment->id;
            }

            $existing = DB::table('exhibition_virtual_environment_versions')
                ->where('environment_id', $environmentId)
                ->where('runtime_sha256', $runtimeHash)
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $nextVersion = ((int) DB::table('exhibition_virtual_environment_versions')
                ->where('environment_id', $environmentId)->max('version_number')) + 1;
            $versionId = DB::table('exhibition_virtual_environment_versions')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'environment_id' => $environmentId,
                'version_number' => $nextVersion,
                'status' => 'ready',
                'viewer_compatibility_version' => (string) $manifest['viewer_compatibility_version'],
                'source_original_name' => (string) ($uploadMetadata['original_name'] ?? basename($sourcePath)),
                'source_disk' => 'local',
                'source_path' => $this->relativePrivatePath($sourcePath),
                'source_size' => (int) data_get($manifest, 'source.bytes'),
                'source_sha256' => strtolower((string) data_get($manifest, 'source.sha256')),
                'runtime_disk' => 'local',
                'runtime_path' => $immutableRelative.'/environment.glb',
                'runtime_size' => (int) data_get($manifest, 'runtime.bytes'),
                'runtime_sha256' => $runtimeHash,
                'collision_disk' => 'local',
                'collision_path' => $immutableRelative.'/collision.glb',
                'collision_size' => (int) data_get($manifest, 'collision.bytes'),
                'collision_sha256' => strtolower((string) data_get($manifest, 'collision.sha256')),
                'license_name' => (string) $license['license_name'],
                'license_code' => $license['license_code'] ?? null,
                'license_url' => (string) $license['license_url'],
                'source_url' => (string) $license['source_url'],
                'creator_name' => (string) $license['creator'],
                'attribution_text' => (string) $license['attribution'],
                'commercial_use_allowed' => true,
                'runtime_derivative_allowed' => true,
                'validation_report' => json_encode([
                    'runtime' => $runtimeValidation['issues'] ?? [],
                    'collision' => $collisionValidation['issues'] ?? [],
                ], JSON_THROW_ON_ERROR),
                'stats_report' => json_encode($stats, JSON_THROW_ON_ERROR),
                'optimization_report' => json_encode($optimization, JSON_THROW_ON_ERROR),
                'metadata_report' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'compatibility_report' => null,
                'created_by' => $actorUserId,
                'published_by' => null,
                'published_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roomIds = [];
            foreach ($metadata['rooms'] as $room) {
                $roomIds[$room['code']] = DB::table('exhibition_virtual_environment_rooms')->insertGetId([
                    'version_id' => $versionId,
                    'room_code' => $room['code'],
                    'title' => $room['title'],
                    'sort_order' => $room['sort_order'],
                    'bounds' => json_encode($room['bounds'], JSON_THROW_ON_ERROR),
                    'metadata' => json_encode($room, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($metadata['slots'] as $slot) {
                DB::table('exhibition_virtual_environment_slots')->insert([
                    'version_id' => $versionId,
                    'room_id' => $roomIds[$slot['room_code']],
                    'slot_code' => $slot['slot_code'],
                    'name' => $slot['name'],
                    'translation' => json_encode($slot['translation'], JSON_THROW_ON_ERROR),
                    'rotation' => json_encode($slot['rotation'], JSON_THROW_ON_ERROR),
                    'max_width_m' => $slot['max_width_m'],
                    'max_height_m' => $slot['max_height_m'],
                    'min_width_m' => $slot['min_width_m'],
                    'min_height_m' => $slot['min_height_m'],
                    'allowed_orientations' => json_encode($slot['allowed_orientations'], JSON_THROW_ON_ERROR),
                    'frame_profile' => $slot['frame_profile'],
                    'enabled_by_default' => (bool) $slot['enabled_by_default'],
                    'source_anchor' => $slot['source_anchor'] ?? null,
                    'metadata' => json_encode($slot, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (array) DB::table('exhibition_virtual_environment_versions')->find($versionId);
        });

        if ($publish) {
            $version = $this->publish((int) $version['id'], $actorUserId);
        }

        return $version;
    }

    /** @return array<string, mixed> */
    public function publish(int $versionId, int $actorUserId, array $occupiedSlotCodes = []): array
    {
        return DB::transaction(function () use ($versionId, $actorUserId, $occupiedSlotCodes): array {
            $version = DB::table('exhibition_virtual_environment_versions')->where('id', $versionId)->lockForUpdate()->first();
            if (! $version) {
                throw new DomainException('The VE-03 environment version does not exist.');
            }
            $environment = DB::table('exhibition_virtual_environments')->where('id', $version->environment_id)->lockForUpdate()->first();
            $candidateSlots = $this->slotsByCode($versionId);
            $currentSlots = $environment?->active_version_id ? $this->slotsByCode((int) $environment->active_version_id) : [];
            $comparison = $this->comparator->compare($currentSlots, $candidateSlots, $occupiedSlotCodes);
            if ($comparison['publishable'] !== true) {
                throw new DomainException('The candidate version would break an occupied artwork slot.');
            }

            if ($environment?->active_version_id && (int) $environment->active_version_id !== $versionId) {
                $this->remapOccupiedSlots((int) $environment->id, (int) $environment->active_version_id, $versionId);
                DB::table('exhibition_virtual_environment_versions')->where('id', $environment->active_version_id)
                    ->update(['status' => 'superseded', 'updated_at' => now()]);
            }
            DB::table('exhibition_virtual_environment_versions')->where('id', $versionId)->update([
                'status' => 'published',
                'compatibility_report' => json_encode($comparison, JSON_THROW_ON_ERROR),
                'published_by' => $actorUserId,
                'published_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('exhibition_virtual_environments')->where('id', $version->environment_id)->update([
                'status' => 'published',
                'active_version_id' => $versionId,
                'updated_by' => $actorUserId,
                'updated_at' => now(),
            ]);

            return (array) DB::table('exhibition_virtual_environment_versions')->find($versionId);
        });
    }

    private function trustedSource(string $path): string
    {
        $actual = realpath($path);
        $root = realpath(Storage::disk('local')->path('exhibitions/ve-03/quarantine'));
        if (! is_string($actual) || ! is_file($actual) || ! is_string($root)
            || ! str_starts_with(strtolower(str_replace('\\', '/', $actual)), rtrim(strtolower(str_replace('\\', '/', $root)), '/').'/')) {
            throw new DomainException('VE-03 source must remain inside private quarantine.');
        }

        return $actual;
    }

    private function existingDirectory(string $path): string
    {
        $actual = realpath($path);
        if (! is_string($actual) || ! is_dir($actual)) {
            throw new DomainException('The VE-03 build directory does not exist.');
        }

        return rtrim($actual, DIRECTORY_SEPARATOR);
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        if (! is_file($path)) {
            throw new DomainException('Missing VE-03 report: '.basename($path));
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DomainException('Malformed VE-03 report: '.basename($path), previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new DomainException('Invalid VE-03 report payload: '.basename($path));
        }

        return $decoded;
    }

    private function guardReports(array $manifest, array $metadata, array $stats, array $runtimeValidation, array $collisionValidation, array $license): void
    {
        if (($runtimeValidation['issues']['numErrors'] ?? 1) !== 0 || ($collisionValidation['issues']['numErrors'] ?? 1) !== 0) {
            throw new DomainException('Khronos validation errors block VE-03 import.');
        }
        $required = $stats['runtime']['extensions_required'] ?? [];
        foreach (['EXT_meshopt_compression', 'KHR_texture_basisu'] as $extension) {
            if (! in_array($extension, $required, true)) {
                throw new DomainException("Required runtime extension {$extension} is missing.");
            }
        }
        if (count($metadata['rooms'] ?? []) < 2 || count($metadata['slots'] ?? []) < 1 || count($metadata['spawns'] ?? []) !== 1
            || (int) ($stats['collision']['triangles'] ?? 0) < 1) {
            throw new DomainException('Rooms, slots, spawn, or collision metadata is incomplete.');
        }
        if (($license['commercial_use_allowed'] ?? false) !== true || ($license['runtime_derivative_allowed'] ?? false) !== true
            || empty($license['attribution']) || empty($manifest['viewer_compatibility_version'])) {
            throw new DomainException('License metadata does not permit the runtime derivative.');
        }
    }

    /** @return array<string, mixed> */
    private function normalizedLicense(array $license, array $uploadMetadata, array $manifest): array
    {
        $sourceHash = strtolower((string) data_get($manifest, 'source.sha256'));

        return array_replace($license, [
            'license_name' => $this->firstNonEmptyString([
                $license['license_name'] ?? null,
                $uploadMetadata['license_name'] ?? null,
            ], 'Internal approved asset'),
            'license_url' => $this->firstNonEmptyString([
                $license['license_url'] ?? null,
                $uploadMetadata['license_url'] ?? null,
            ], 'internal://exhibitions/ve-03/license'),
            'source_url' => $this->firstNonEmptyString([
                $license['source_url'] ?? null,
                $uploadMetadata['source_url'] ?? null,
            ], 'internal://exhibitions/ve-03/source/'.$sourceHash),
            'creator' => $this->firstNonEmptyString([
                $license['creator'] ?? null,
                $uploadMetadata['creator_name'] ?? null,
            ], 'Platform administrator'),
            'attribution' => $this->firstNonEmptyString([
                $license['attribution'] ?? null,
                $uploadMetadata['attribution_text'] ?? null,
            ], 'Internal platform asset.'),
            'commercial_use_allowed' => is_bool($license['commercial_use_allowed'] ?? null)
                ? $license['commercial_use_allowed']
                : (bool) ($uploadMetadata['commercial_use_allowed'] ?? false),
            'runtime_derivative_allowed' => is_bool($license['runtime_derivative_allowed'] ?? null)
                ? $license['runtime_derivative_allowed']
                : (bool) ($uploadMetadata['runtime_derivative_allowed'] ?? false),
        ]);
    }

    /** @param array<string, mixed> $uploadMetadata @param array<string, mixed> $manifest */
    private function environmentCode(array $uploadMetadata, array $manifest, string $sourceHash): string
    {
        if ((int) ($uploadMetadata['replacement_environment_id'] ?? 0) > 0) {
            return '';
        }

        $name = pathinfo((string) ($uploadMetadata['original_name'] ?? ''), PATHINFO_FILENAME);
        $slug = Str::slug($name !== '' ? $name : (string) ($manifest['environment_code'] ?? 'hall'));
        $slug = $slug !== '' ? $slug : 'hall';

        return substr($slug, 0, 55).'-'.substr($sourceHash, 0, 16);
    }

    /** @param array<string, mixed> $uploadMetadata @param array<string, mixed> $manifest */
    private function environmentTitle(array $uploadMetadata, array $manifest, string $sourceHash): string
    {
        $manifestTitle = trim((string) ($manifest['environment_title'] ?? ''));
        if ($manifestTitle !== '') {
            return substr($manifestTitle, 0, 180);
        }

        $name = pathinfo((string) ($uploadMetadata['original_name'] ?? ''), PATHINFO_FILENAME);
        if ($name !== '') {
            return substr((string) Str::of($name)->replace(['-', '_'], ' ')->squish()->title(), 0, 180);
        }

        return '3D Hall '.strtoupper(substr($sourceHash, 0, 8));
    }

    /** @param array<int, mixed> $values */
    private function firstNonEmptyString(array $values, string $fallback): string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    private function guardArtifact(string $path, string $expectedHash, int $expectedSize): void
    {
        if (! is_file($path) || filesize($path) !== $expectedSize
            || ! hash_equals(strtolower($expectedHash), strtolower((string) hash_file('sha256', $path)))) {
            throw new DomainException('VE-03 artifact integrity verification failed for '.basename($path).'.');
        }
    }

    private function copyImmutable(string $buildDirectory, string $targetDirectory): void
    {
        if (! is_dir($targetDirectory)) {
            $this->files->makeDirectory($targetDirectory, 0750, true);
        }
        foreach (glob($buildDirectory.'/*') ?: [] as $source) {
            if (! is_file($source) || basename($source) === 'environment.meshopt.glb' || basename($source) === 'environment.ktx2.glb') {
                continue;
            }
            $target = $targetDirectory.'/'.basename($source);
            if (is_file($target)) {
                if (! hash_equals((string) hash_file('sha256', $source), (string) hash_file('sha256', $target))) {
                    throw new DomainException('An immutable VE-03 runtime path already contains different bytes.');
                }

                continue;
            }
            if (! $this->files->copy($source, $target)) {
                throw new DomainException('Unable to copy the immutable VE-03 runtime artifact.');
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function slotsByCode(int $versionId): array
    {
        return DB::table('exhibition_virtual_environment_slots')->where('version_id', $versionId)->get()
            ->mapWithKeys(fn (object $slot): array => [$slot->slot_code => [
                'translation' => json_decode((string) $slot->translation, true, 512, JSON_THROW_ON_ERROR),
                'rotation' => json_decode((string) $slot->rotation, true, 512, JSON_THROW_ON_ERROR),
                'max_width_m' => (float) $slot->max_width_m,
                'max_height_m' => (float) $slot->max_height_m,
            ]])->all();
    }

    private function remapOccupiedSlots(int $environmentId, int $currentVersionId, int $candidateVersionId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('exhibition_virtual_slot_occupancies')) {
            return;
        }

        $currentSlots = DB::table('exhibition_virtual_environment_slots')
            ->where('version_id', $currentVersionId)
            ->pluck('slot_code', 'id');
        $candidateSlots = DB::table('exhibition_virtual_environment_slots')
            ->where('version_id', $candidateVersionId)
            ->pluck('id', 'slot_code');

        $occupancies = DB::table('exhibition_virtual_slot_occupancies')
            ->where('environment_id', $environmentId)
            ->where('version_id', $currentVersionId)
            ->lockForUpdate()
            ->get();

        foreach ($occupancies as $occupancy) {
            $slotCode = $currentSlots[(int) $occupancy->slot_id] ?? null;
            $candidateSlotId = $slotCode ? ($candidateSlots[$slotCode] ?? null) : null;
            if (! $candidateSlotId) {
                throw new DomainException('An occupied VE-03 slot cannot be mapped to the candidate environment version.');
            }

            DB::table('exhibition_virtual_placements')->where('id', $occupancy->placement_id)->update([
                'version_id' => $candidateVersionId,
                'slot_id' => $candidateSlotId,
                'updated_at' => now(),
            ]);
            DB::table('exhibition_virtual_slot_occupancies')->where('id', $occupancy->id)->update([
                'version_id' => $candidateVersionId,
                'slot_id' => $candidateSlotId,
                'updated_at' => now(),
            ]);
        }

        if (DB::getSchemaBuilder()->hasTable('exhibition_virtual_slot_holds')) {
            DB::table('exhibition_virtual_slot_holds')
                ->where('environment_id', $environmentId)
                ->where('version_id', $currentVersionId)
                ->delete();
        }
    }

    private function relativePrivatePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen(storage_path('app/private')))), '/');
    }
}
