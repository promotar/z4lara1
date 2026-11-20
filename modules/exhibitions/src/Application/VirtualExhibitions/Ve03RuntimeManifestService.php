<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class Ve03RuntimeManifestService
{
    public function __construct(private readonly Ve03ArtworkProjectionService $artworks) {}

    /** @return array{environment: object, version: object, manifest: array<string, mixed>, etag: string}|null */
    public function activeForRoom(object $room): ?array
    {
        $active = $this->activeEnvironmentAndVersion(
            environmentId: isset($room->environment_id) ? (int) $room->environment_id : null,
        );
        if ($active === null) {
            return null;
        }
        ['environment' => $environment, 'version' => $version] = $active;

        $metadata = $this->decode((string) $version->metadata_report);
        $spawn = $metadata['spawns'][0] ?? null;
        if (! is_array($spawn) || count($spawn['translation'] ?? []) !== 3) {
            return null;
        }

        $rooms = DB::table('exhibition_virtual_environment_rooms')
            ->where('version_id', $version->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (object $record): array => [
                'code' => (string) $record->room_code,
                'title' => (string) $record->title,
                'sort_order' => (int) $record->sort_order,
                'bounds' => $this->decode((string) $record->bounds),
            ])->all();
        if ($rooms === []) {
            return null;
        }
        $placements = $this->artworks->publicPlacements($room, $version);
        $safetyDisplays = DB::table('exhibition_virtual_environment_slots')
            ->where('version_id', $version->id)
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (object $slot, int $index): array => [
                'id' => 'DISPLAY_'.($index + 1),
                'dimensions' => [
                    'width_m' => (float) $slot->max_width_m,
                    'height_m' => (float) $slot->max_height_m,
                ],
                'transform' => [
                    'position' => array_map('floatval', $this->decode((string) $slot->translation)),
                    'quaternion' => array_map('floatval', $this->decode((string) $slot->rotation)),
                    'outward_offset_m' => 0.035,
                ],
            ])->all();

        $runtimeHash = strtolower((string) $version->runtime_sha256);
        $manifest = [
            'schema_version' => 1,
            'exhibition' => [
                'id' => (string) $room->public_id,
                'slug' => (string) $room->slug,
                'title' => (string) $room->title,
                'version' => (int) $version->version_number,
            ],
            'environment' => [
                'version_id' => (string) $version->public_id,
                'url' => route('exhibitions.virtual.ve03.asset', [$runtimeHash, 'environment']),
                'collision_url' => route('exhibitions.virtual.ve03.asset', [$runtimeHash, 'collision']),
                'hash' => 'sha256-'.$runtimeHash,
                'bytes' => (int) $version->runtime_size,
                'collision_bytes' => (int) $version->collision_size,
                'compression' => 'meshopt',
                'texture_format' => 'ktx2',
                // The source GLB combines all authored empty ornate frames in
                // this material. Visitor mode hides it and renders only the
                // dynamic frames belonging to published placements.
                'hidden_empty_frame_materials' => ['Picture_Frame'],
            ],
            'spawn' => [
                'position' => array_map('floatval', $spawn['translation']),
                'yaw' => deg2rad((float) ($spawn['yaw_degrees'] ?? 0)),
                'pitch' => deg2rad((float) ($spawn['pitch_degrees'] ?? 0)),
                'room_code' => (string) ($spawn['room_code'] ?? ''),
            ],
            'viewer' => [
                'walk_speed' => (float) config('exhibitions.virtual_exhibitions.ve03.walk_speed', 2.2),
                'max_step_height' => (float) config('exhibitions.virtual_exhibitions.ve03.max_step_height', 0.28),
                'max_slope_degrees' => (float) config('exhibitions.virtual_exhibitions.ve03.max_slope_degrees', 42),
                'interaction_distance' => (float) config('exhibitions.virtual_exhibitions.ve03.interaction_distance', 3.0),
                'artwork_safety_distance' => (float) config('exhibitions.virtual_exhibitions.ve03.artwork_safety_distance', 1.0),
                'artwork_safety_side_margin' => (float) config('exhibitions.virtual_exhibitions.ve03.artwork_safety_side_margin', 0.4),
            ],
            'rooms' => $rooms,
            // Safety remains defined by configured display positions even
            // though unpublished authored frames and image planes are hidden.
            'safety_displays' => $safetyDisplays,
            'placements' => $placements,
            'availability_version' => (int) sprintf('%u', crc32(json_encode($placements, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))),
        ];
        $encoded = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'environment' => $environment,
            'version' => $version,
            'manifest' => $manifest,
            'etag' => hash('sha256', $encoded),
        ];
    }

    /** @return array{artworks_count:int,displays_count:int}|null */
    public function activePublicStatistics(object $room): ?array
    {
        // Statistics only need the published database state. Full artifact hash
        // validation remains on room entry and would make this lightweight index
        // card hash the complete 3D and collision files on every page view.
        $active = $this->activeEnvironmentAndVersion(
            validateArtifacts: false,
            environmentId: isset($room->environment_id) ? (int) $room->environment_id : null,
        );
        if ($active === null || ! Schema::hasTable('exhibition_virtual_slot_occupancies')) {
            return null;
        }
        $version = $active['version'];
        $displays = DB::table('exhibition_virtual_environment_slots')
            ->where('version_id', $version->id)
            ->where('enabled_by_default', true)
            ->count();

        $direct = DB::table('exhibition_virtual_slot_occupancies as occupancy')
            ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'occupancy.placement_id')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
            ->join('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
            ->join('exhibition_artwork_media as media', function ($join): void {
                $join->on('media.artwork_id', '=', 'artwork.id')
                    ->where('media.state', 'ready')
                    ->where('media.is_primary', true);
            })
            ->where('occupancy.version_id', $version->id)
            ->whereIn('placement.state', ['active', 'sold'])
            ->where('artwork.state', 'published')
            ->distinct()
            ->count('placement.id');

        $legacy = 0;
        if (! ($room->is_ve03_environment ?? false)) {
            $legacy = DB::table('exhibition_virtual_placement_requests as placement')
                ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
                ->join('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
                ->join('exhibition_artwork_media as media', function ($join): void {
                    $join->on('media.artwork_id', '=', 'artwork.id')
                        ->where('media.state', 'ready')
                        ->where('media.is_primary', true);
                })
                ->where('placement.room_id', $room->id)
                ->where('placement.status', 'published')
                ->where('artwork.state', 'published')
                ->where('publication.state', 'published')
                ->distinct()
                ->count('placement.id');
        }

        return [
            'artworks_count' => min($displays, $direct + $legacy),
            'displays_count' => $displays,
        ];
    }

    /** @return Collection<int, object> */
    public function publishedRooms(): Collection
    {
        if (! $this->publicTablesAreAvailable()) {
            return collect();
        }

        return DB::table('exhibition_virtual_environments as environment')
            ->join('exhibition_virtual_environment_versions as version', function ($join): void {
                $join->on('version.id', '=', 'environment.active_version_id')
                    ->on('version.environment_id', '=', 'environment.id')
                    ->where('version.status', 'published');
            })
            ->where('environment.status', 'published')
            ->whereNotNull('environment.active_version_id')
            ->orderByDesc('version.published_at')
            ->orderBy('environment.title')
            ->get([
                'environment.id',
                'environment.public_id',
                'environment.code',
                'environment.title',
                'version.published_at',
            ])
            ->map(function (object $environment): object {
                $room = $this->publicRoomRecord($environment);
                $statistics = $this->activePublicStatistics($room);
                $room->artworks_count = (int) ($statistics['artworks_count'] ?? 0);
                $room->displays_count = (int) ($statistics['displays_count'] ?? 0);

                return $room;
            });
    }

    public function publishedRoom(string $identifier): ?object
    {
        if (! $this->publicTablesAreAvailable()) {
            return null;
        }

        $environment = DB::table('exhibition_virtual_environments as environment')
            ->join('exhibition_virtual_environment_versions as version', function ($join): void {
                $join->on('version.id', '=', 'environment.active_version_id')
                    ->on('version.environment_id', '=', 'environment.id')
                    ->where('version.status', 'published');
            })
            ->where('environment.status', 'published')
            ->whereNotNull('environment.active_version_id')
            ->where(function ($query) use ($identifier): void {
                $query->where('environment.public_id', $identifier)
                    ->orWhere(function ($query) use ($identifier): void {
                        $query->whereNotNull('environment.code')
                            ->where('environment.code', '<>', '')
                            ->where('environment.code', $identifier);
                    });
            })
            ->first([
                'environment.id',
                'environment.public_id',
                'environment.code',
                'environment.title',
                'version.published_at',
            ]);

        return $environment ? $this->publicRoomRecord($environment) : null;
    }

    /** @return array{version: object, path: string, hash: string, size: int} */
    public function publishedArtifact(string $runtimeHash, string $asset): array
    {
        if (! in_array($asset, ['environment', 'collision'], true) || preg_match('/^[a-f0-9]{64}$/', $runtimeHash) !== 1) {
            throw new DomainException('Invalid VE-03 runtime asset address.');
        }

        $version = DB::table('exhibition_virtual_environment_versions')
            ->where('runtime_sha256', strtolower($runtimeHash))
            ->where('status', 'published')
            ->first();
        if (! $version || ! $this->artifactIsUsable($version, $asset)) {
            throw new DomainException('Published VE-03 runtime asset is unavailable.');
        }

        $pathColumn = $asset === 'environment' ? 'runtime_path' : 'collision_path';
        $hashColumn = $asset === 'environment' ? 'runtime_sha256' : 'collision_sha256';
        $sizeColumn = $asset === 'environment' ? 'runtime_size' : 'collision_size';

        return [
            'version' => $version,
            'path' => Storage::disk('local')->path((string) $version->{$pathColumn}),
            'hash' => strtolower((string) $version->{$hashColumn}),
            'size' => (int) $version->{$sizeColumn},
        ];
    }

    private function artifactIsUsable(object $version, string $asset): bool
    {
        $pathColumn = $asset === 'environment' ? 'runtime_path' : 'collision_path';
        $hashColumn = $asset === 'environment' ? 'runtime_sha256' : 'collision_sha256';
        $sizeColumn = $asset === 'environment' ? 'runtime_size' : 'collision_size';
        $path = Storage::disk('local')->path((string) $version->{$pathColumn});
        $actual = realpath($path);
        $trusted = realpath(Storage::disk('local')->path('exhibitions/ve-03/runtime'));
        if (! is_string($actual) || ! is_file($actual) || ! is_string($trusted)
            || ! str_starts_with(strtolower(str_replace('\\', '/', $actual)), rtrim(strtolower(str_replace('\\', '/', $trusted)), '/').'/')) {
            return false;
        }

        return filesize($actual) === (int) $version->{$sizeColumn}
            && hash_equals(strtolower((string) $version->{$hashColumn}), strtolower((string) hash_file('sha256', $actual)));
    }

    /** @return array{environment:object,version:object}|null */
    private function activeEnvironmentAndVersion(bool $validateArtifacts = true, ?int $environmentId = null): ?array
    {
        if (! config('exhibitions.virtual_exhibitions.ve03.public_viewer_enabled', true)
            || ! Schema::hasTable('exhibition_virtual_environments')
            || ! Schema::hasTable('exhibition_virtual_environment_versions')) {
            return null;
        }

        $environmentQuery = DB::table('exhibition_virtual_environments')
            ->where('status', 'published')
            ->whereNotNull('active_version_id');
        if ($environmentId !== null) {
            $environmentQuery->where('id', $environmentId);
        }
        $environment = $environmentQuery->orderBy('id')->first();
        if (! $environment) {
            return null;
        }

        $version = DB::table('exhibition_virtual_environment_versions')
            ->where('id', $environment->active_version_id)
            ->where('environment_id', $environment->id)
            ->where('status', 'published')
            ->first();
        if (! $version || ($validateArtifacts
            && (! $this->artifactIsUsable($version, 'environment') || ! $this->artifactIsUsable($version, 'collision')))) {
            return null;
        }

        return compact('environment', 'version');
    }

    private function publicTablesAreAvailable(): bool
    {
        return config('exhibitions.virtual_exhibitions.ve03.public_viewer_enabled', true)
            && Schema::hasTable('exhibition_virtual_environments')
            && Schema::hasTable('exhibition_virtual_environment_versions');
    }

    private function publicRoomRecord(object $environment): object
    {
        $code = trim((string) ($environment->code ?? ''));

        return (object) [
            'id' => (int) $environment->id,
            'environment_id' => (int) $environment->id,
            'public_id' => (string) $environment->public_id,
            'slug' => $code !== '' ? $code : (string) $environment->public_id,
            'title' => (string) $environment->title,
            'short_description' => null,
            'published_at' => $environment->published_at,
            'is_ve03_environment' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
