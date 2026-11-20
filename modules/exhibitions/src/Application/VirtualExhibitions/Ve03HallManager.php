<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Ve03HallManager
{
    /** @return array<string, mixed> */
    public function duplicate(string $versionPublicId, int $actorUserId): array
    {
        return DB::transaction(function () use ($versionPublicId, $actorUserId): array {
            $source = DB::table('exhibition_virtual_environment_versions')
                ->where('public_id', $versionPublicId)
                ->lockForUpdate()
                ->first();
            if (! $source) {
                throw new DomainException('The selected 3D hall version does not exist.');
            }

            $sourceEnvironment = DB::table('exhibition_virtual_environments')
                ->where('id', $source->environment_id)
                ->lockForUpdate()
                ->first();
            if (! $sourceEnvironment) {
                throw new DomainException('The selected 3D hall environment does not exist.');
            }

            $environmentId = DB::table('exhibition_virtual_environments')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'code' => 'hall-copy-'.str_replace('-', '', (string) Str::uuid()),
                'title' => substr((string) $sourceEnvironment->title.' Copy', 0, 180),
                'status' => 'draft',
                'active_version_id' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $versionId = DB::table('exhibition_virtual_environment_versions')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'environment_id' => $environmentId,
                'version_number' => 1,
                'status' => 'ready',
                'viewer_compatibility_version' => $source->viewer_compatibility_version,
                'source_original_name' => $source->source_original_name,
                'source_disk' => $source->source_disk,
                'source_path' => $source->source_path,
                'source_size' => $source->source_size,
                'source_sha256' => $source->source_sha256,
                'runtime_disk' => $source->runtime_disk,
                'runtime_path' => $source->runtime_path,
                'runtime_size' => $source->runtime_size,
                'runtime_sha256' => $source->runtime_sha256,
                'collision_disk' => $source->collision_disk,
                'collision_path' => $source->collision_path,
                'collision_size' => $source->collision_size,
                'collision_sha256' => $source->collision_sha256,
                'license_name' => $source->license_name,
                'license_code' => $source->license_code,
                'license_url' => $source->license_url,
                'source_url' => $source->source_url,
                'creator_name' => $source->creator_name,
                'attribution_text' => $source->attribution_text,
                'commercial_use_allowed' => $source->commercial_use_allowed,
                'runtime_derivative_allowed' => $source->runtime_derivative_allowed,
                'validation_report' => $source->validation_report,
                'stats_report' => $source->stats_report,
                'optimization_report' => $source->optimization_report,
                'metadata_report' => $source->metadata_report,
                'compatibility_report' => null,
                'created_by' => $actorUserId,
                'published_by' => null,
                'published_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roomIds = [];
            foreach (DB::table('exhibition_virtual_environment_rooms')->where('version_id', $source->id)->orderBy('id')->get() as $room) {
                $roomIds[(int) $room->id] = DB::table('exhibition_virtual_environment_rooms')->insertGetId([
                    'version_id' => $versionId,
                    'room_code' => $room->room_code,
                    'title' => $room->title,
                    'sort_order' => $room->sort_order,
                    'bounds' => $room->bounds,
                    'metadata' => $room->metadata,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (DB::table('exhibition_virtual_environment_slots')->where('version_id', $source->id)->orderBy('id')->get() as $slot) {
                $roomId = $roomIds[(int) $slot->room_id] ?? null;
                if (! $roomId) {
                    throw new DomainException('The hall contains a display position without a valid room.');
                }
                DB::table('exhibition_virtual_environment_slots')->insert([
                    'version_id' => $versionId,
                    'room_id' => $roomId,
                    'slot_code' => $slot->slot_code,
                    'name' => $slot->name,
                    'translation' => $slot->translation,
                    'rotation' => $slot->rotation,
                    'max_width_m' => $slot->max_width_m,
                    'max_height_m' => $slot->max_height_m,
                    'min_width_m' => $slot->min_width_m,
                    'min_height_m' => $slot->min_height_m,
                    'allowed_orientations' => $slot->allowed_orientations,
                    'frame_profile' => $slot->frame_profile,
                    'enabled_by_default' => $slot->enabled_by_default,
                    'source_anchor' => $slot->source_anchor,
                    'metadata' => $slot->metadata,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (array) DB::table('exhibition_virtual_environment_versions')->find($versionId);
        });
    }

    public function delete(string $versionPublicId, int $actorUserId): void
    {
        $cleanup = DB::transaction(function () use ($versionPublicId, $actorUserId): array {
            $version = DB::table('exhibition_virtual_environment_versions')
                ->where('public_id', $versionPublicId)
                ->lockForUpdate()
                ->first();
            if (! $version) {
                throw new DomainException('The selected 3D hall version does not exist.');
            }
            $this->guardNoLinkedArtwork((int) $version->id);

            $environment = DB::table('exhibition_virtual_environments')
                ->where('id', $version->environment_id)
                ->lockForUpdate()
                ->first();
            if (! $environment) {
                throw new DomainException('The 3D hall environment does not exist.');
            }

            if ((int) $environment->active_version_id === (int) $version->id) {
                $fallback = DB::table('exhibition_virtual_environment_versions')
                    ->where('environment_id', $environment->id)
                    ->where('id', '!=', $version->id)
                    ->orderByDesc('version_number')
                    ->lockForUpdate()
                    ->first();
                if ($fallback) {
                    DB::table('exhibition_virtual_environment_versions')->where('id', $fallback->id)->update([
                        'status' => 'published',
                        'published_by' => $actorUserId,
                        'published_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update([
                        'status' => 'published',
                        'active_version_id' => $fallback->id,
                        'updated_by' => $actorUserId,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update([
                        'status' => 'draft',
                        'active_version_id' => null,
                        'updated_by' => $actorUserId,
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('exhibition_virtual_environment_versions')->where('id', $version->id)->delete();
            if (! DB::table('exhibition_virtual_environment_versions')->where('environment_id', $environment->id)->exists()) {
                DB::table('exhibition_virtual_environments')->where('id', $environment->id)->delete();
            }

            return [
                'runtime_path' => (string) $version->runtime_path,
                'source_sha256' => strtolower((string) $version->source_sha256),
            ];
        });

        if (! DB::table('exhibition_virtual_environment_versions')->where('runtime_path', $cleanup['runtime_path'])->exists()) {
            Storage::disk('local')->deleteDirectory(str_replace('\\', '/', dirname($cleanup['runtime_path'])));
        }
        if (! DB::table('exhibition_virtual_environment_versions')->where('source_sha256', $cleanup['source_sha256'])->exists()) {
            Storage::disk('local')->deleteDirectory('exhibitions/ve-03/quarantine/uploads/'.$cleanup['source_sha256']);
        }
    }

    /** @param array<string, mixed> $roomTitles */
    public function updatePresentation(string $versionPublicId, int $actorUserId, string $title, array $roomTitles): void
    {
        DB::transaction(function () use ($versionPublicId, $actorUserId, $title, $roomTitles): void {
            $version = DB::table('exhibition_virtual_environment_versions')
                ->where('public_id', $versionPublicId)
                ->lockForUpdate()
                ->first();
            if (! $version) {
                throw new DomainException('The selected 3D hall version does not exist.');
            }

            $environment = DB::table('exhibition_virtual_environments')
                ->where('id', $version->environment_id)
                ->lockForUpdate()
                ->first();
            if (! $environment) {
                throw new DomainException('The 3D hall environment does not exist.');
            }

            $title = trim($title);
            if ($title === '') {
                throw new DomainException('Enter a hall name.');
            }

            $rooms = DB::table('exhibition_virtual_environment_rooms')
                ->where('version_id', $version->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('room_code');
            foreach ($roomTitles as $roomCode => $roomTitle) {
                $roomCode = (string) $roomCode;
                if (! $rooms->has($roomCode)) {
                    throw new DomainException('One of the selected hall rooms no longer exists.');
                }
                $roomTitle = trim((string) $roomTitle);
                if ($roomTitle === '') {
                    throw new DomainException('Every room must have a display name.');
                }

                $room = $rooms->get($roomCode);
                $metadata = json_decode((string) $room->metadata, true) ?: [];
                $metadata['title'] = $roomTitle;
                DB::table('exhibition_virtual_environment_rooms')->where('id', $room->id)->update([
                    'title' => $roomTitle,
                    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }

            $metadataReport = json_decode((string) $version->metadata_report, true) ?: [];
            if (is_array($metadataReport['rooms'] ?? null)) {
                foreach ($metadataReport['rooms'] as &$metadataRoom) {
                    $code = is_array($metadataRoom) ? (string) ($metadataRoom['code'] ?? '') : '';
                    if ($code !== '' && array_key_exists($code, $roomTitles)) {
                        $metadataRoom['title'] = trim((string) $roomTitles[$code]);
                    }
                }
                unset($metadataRoom);
            }

            DB::table('exhibition_virtual_environment_versions')->where('id', $version->id)->update([
                'metadata_report' => json_encode($metadataReport, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update([
                'title' => $title,
                'updated_by' => $actorUserId,
                'updated_at' => now(),
            ]);
        });
    }

    private function guardNoLinkedArtwork(int $versionId): void
    {
        foreach (['exhibition_virtual_slot_occupancies', 'exhibition_virtual_placements'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('version_id', $versionId)->exists()) {
                throw new DomainException('Remove all artwork placements from this hall before deleting it.');
            }
        }
    }
}
