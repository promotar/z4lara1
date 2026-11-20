<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_virtual_environments')
            || ! Schema::hasTable('exhibition_virtual_environment_versions')) {
            return;
        }

        DB::transaction(function (): void {
            $legacyEnvironments = DB::table('exhibition_virtual_environments')
                ->where('code', '')
                ->lockForUpdate()
                ->get();

            foreach ($legacyEnvironments as $environment) {
                $versions = DB::table('exhibition_virtual_environment_versions')
                    ->where('environment_id', $environment->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $groups = $versions->groupBy(fn (object $version): string => strtolower((string) $version->source_sha256));
                if ($groups->count() < 2) {
                    continue;
                }

                $activeVersionId = (int) ($environment->active_version_id ?? 0);
                $keepHash = (string) ($groups->first(
                    fn ($group): bool => $group->contains(fn (object $version): bool => (int) $version->id === $activeVersionId),
                )?->first()?->source_sha256 ?? $groups->keys()->first());

                foreach ($groups as $sourceHash => $group) {
                    if (hash_equals(strtolower($keepHash), strtolower((string) $sourceHash))) {
                        if ((string) $environment->title === 'Art INPA Gallery House') {
                            DB::table('exhibition_virtual_environments')->where('id', $environment->id)->update([
                                'title' => $this->titleFor($group->first(), (string) $sourceHash),
                                'updated_at' => now(),
                            ]);
                        }

                        continue;
                    }

                    $published = $group->whereNotNull('published_at')->sortByDesc('published_at')->first();
                    $environmentId = DB::table('exhibition_virtual_environments')->insertGetId([
                        'public_id' => (string) Str::uuid(),
                        'code' => $this->uniqueCode((string) $sourceHash),
                        'title' => $this->titleFor($group->first(), (string) $sourceHash),
                        'status' => $published ? 'published' : 'draft',
                        'active_version_id' => $published?->id,
                        'created_by' => (int) $environment->created_by,
                        'updated_by' => (int) $environment->updated_by,
                        'created_at' => $environment->created_at,
                        'updated_at' => now(),
                    ]);

                    foreach ($group->values() as $index => $version) {
                        $status = $published && (int) $published->id === (int) $version->id
                            ? 'published'
                            : ($version->status === 'published' ? 'superseded' : $version->status);
                        DB::table('exhibition_virtual_environment_versions')->where('id', $version->id)->update([
                            'environment_id' => $environmentId,
                            'version_number' => $index + 1,
                            'status' => $status,
                            'updated_at' => now(),
                        ]);
                    }

                    $versionIds = $group->pluck('id')->map(fn ($id): int => (int) $id)->all();
                    foreach (['exhibition_virtual_placements', 'exhibition_virtual_slot_occupancies', 'exhibition_virtual_slot_holds'] as $table) {
                        if (Schema::hasTable($table)) {
                            DB::table($table)->whereIn('version_id', $versionIds)->update(['environment_id' => $environmentId]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Existing hall records must never be merged again during rollback.
    }

    private function uniqueCode(string $sourceHash): string
    {
        $base = 'hall-'.substr(strtolower($sourceHash), 0, 24);
        if (! DB::table('exhibition_virtual_environments')->where('code', $base)->exists()) {
            return $base;
        }

        return 'hall-'.str_replace('-', '', (string) Str::uuid());
    }

    private function titleFor(object $version, string $sourceHash): string
    {
        $original = pathinfo((string) $version->source_original_name, PATHINFO_FILENAME);
        if ($original !== '' && strtolower($original) !== 'source') {
            return substr((string) Str::of($original)->replace(['-', '_'], ' ')->squish()->title(), 0, 180);
        }

        $metadata = json_decode((string) $version->metadata_report, true) ?: [];
        $roomTitle = trim((string) data_get($metadata, 'rooms.0.title', ''));
        if ($roomTitle !== '') {
            $roomTitle = (string) preg_replace('/\s+(atrium|rotunda|gallery\s*\d*|room\s*\d*)$/i', '', $roomTitle);

            return substr(trim($roomTitle) !== '' ? trim($roomTitle) : '3D Hall '.strtoupper(substr($sourceHash, 0, 8)), 0, 180);
        }

        return '3D Hall '.strtoupper(substr($sourceHash, 0, 8));
    }
};
