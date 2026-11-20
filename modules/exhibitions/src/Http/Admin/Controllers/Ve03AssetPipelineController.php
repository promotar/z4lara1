<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03AssetVersionImporter;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03HallManager;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03ProcessedBuildStager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class Ve03AssetPipelineController extends Controller
{
    public function halls(): Response
    {
        return response()->view('exhibitions::admin.virtual.ve03-halls-index', [
            'versions' => $this->versions(),
        ]);
    }

    public function index(Request $request): Response
    {
        $versions = $this->versions();
        $replacementTarget = null;
        if ($request->filled('replaces')) {
            $replacementTarget = $versions->firstWhere('public_id', (string) $request->query('replaces'));
            abort_unless($replacementTarget, 404);
        }

        $disk = Storage::disk('local');
        $uploads = collect($disk->directories('exhibitions/ve-03/quarantine/uploads'))
            ->map(function (string $directory) use ($disk, $versions): object {
                $metadataPath = $directory.'/upload-metadata.json';
                $metadata = $disk->exists($metadataPath)
                    ? (json_decode((string) $disk->get($metadataPath), true) ?: [])
                    : [];
                $stagedBuildPath = (string) ($metadata['staged_build_path'] ?? '');
                $buildReady = $stagedBuildPath !== ''
                    && str_starts_with($stagedBuildPath, $directory.'/builds/')
                    && collect(Ve03ProcessedBuildStager::requiredFiles())
                        ->every(fn (string $name): bool => $disk->exists($stagedBuildPath.'/'.$name));
                $sha256 = (string) ($metadata['sha256'] ?? basename($directory));

                $matchingVersion = $versions->first(
                    fn (object $version): bool => hash_equals(strtolower((string) $version->source_sha256), strtolower($sha256)),
                );

                return (object) [
                    'sha256' => $sha256,
                    'original_name' => (string) ($metadata['original_name'] ?? 'GLB source'),
                    'creator_name' => (string) ($metadata['creator_name'] ?? 'Unknown creator'),
                    'source_url' => (string) ($metadata['source_url'] ?? ''),
                    'license_name' => (string) ($metadata['license_name'] ?? ''),
                    'license_url' => (string) ($metadata['license_url'] ?? ''),
                    'attribution_text' => (string) ($metadata['attribution_text'] ?? ''),
                    'uploaded_at' => (string) ($metadata['uploaded_at'] ?? ''),
                    'status' => (string) ($metadata['status'] ?? 'quarantined_not_processed'),
                    'bytes' => (int) ($metadata['bytes'] ?? 0),
                    'build_ready' => $buildReady,
                    'runtime_sha256' => (string) ($metadata['runtime_sha256'] ?? ''),
                    'collision_sha256' => (string) ($metadata['collision_sha256'] ?? ''),
                    'room_count' => (int) ($metadata['room_count'] ?? 0),
                    'slot_count' => (int) ($metadata['slot_count'] ?? 0),
                    'imported' => $matchingVersion !== null,
                    'version_status' => (string) ($matchingVersion->status ?? ''),
                ];
            })
            ->sortByDesc('uploaded_at')
            ->values();

        $activeUpload = $uploads->first();
        if ($request->boolean('new')) {
            $workflow = ['upload' => 'current', 'import' => 'pending', 'publish' => 'pending'];
        } elseif ($activeUpload && ! (! $activeUpload->imported && in_array($activeUpload->status, ['imported', 'published'], true))) {
            $workflow = [
                'upload' => ($activeUpload->build_ready || $activeUpload->imported) ? 'complete' : 'current',
                'import' => $activeUpload->imported ? 'complete' : ($activeUpload->build_ready ? 'current' : 'pending'),
                'publish' => $activeUpload->version_status === 'published' ? 'complete' : ($activeUpload->imported ? 'current' : 'pending'),
            ];
        } elseif ($versions->isNotEmpty()) {
            $hasPublishedVersion = $versions->contains(fn (object $version): bool => $version->status === 'published');
            $workflow = ['upload' => 'complete', 'import' => 'complete', 'publish' => $hasPublishedVersion ? 'complete' : 'current'];
        } else {
            $workflow = ['upload' => 'current', 'import' => 'pending', 'publish' => 'pending'];
        }

        return response()->view('exhibitions::admin.virtual.ve03-asset-pipeline', [
            'versions' => $versions,
            'uploads' => $uploads,
            'activeUpload' => $activeUpload,
            'workflow' => $workflow,
            'requiredBuildFiles' => Ve03ProcessedBuildStager::requiredFiles(),
            'startingNewHall' => $request->boolean('new'),
            'replacementTarget' => $replacementTarget,
        ]);
    }

    public function upload(Request $request, Ve03ProcessedBuildStager $stager): RedirectResponse
    {
        $request->validate([
            'asset' => ['required', 'file', 'max:102400'],
            'build_bundle' => ['required', 'file', 'max:204800'],
            'replaces' => ['nullable', 'uuid'],
        ]);
        $file = $request->file('asset');
        if (! $file || strtolower($file->getClientOriginalExtension()) !== 'glb') {
            return back()->withErrors(['asset' => 'Only a GLB source is accepted into VE-03 quarantine.']);
        }
        $path = $file->getRealPath();
        $header = is_string($path) ? file_get_contents($path, false, null, 0, 12) : false;
        $unpacked = is_string($header) && strlen($header) === 12 ? unpack('a4magic/Vversion/Vlength', $header) : false;
        if (! is_array($unpacked) || ($unpacked['magic'] ?? null) !== 'glTF' || ($unpacked['version'] ?? null) !== 2
            || ($unpacked['length'] ?? null) !== $file->getSize()) {
            return back()->withErrors(['asset' => 'The upload is not a structurally valid GLB 2.0 container.']);
        }

        $hash = strtolower((string) hash_file('sha256', $path));
        $base = 'exhibitions/ve-03/quarantine/uploads/'.$hash;
        $stored = Storage::disk('local')->putFileAs($base.'/source', $file, 'source.glb');
        if (! is_string($stored)) {
            throw new DomainException('Unable to write the VE-03 source to private quarantine.');
        }
        $replacement = null;
        if ($request->filled('replaces')) {
            $replacement = DB::table('exhibition_virtual_environment_versions')
                ->where('public_id', (string) $request->input('replaces'))
                ->first();
            if (! $replacement) {
                return back()->withErrors(['asset' => 'The hall selected for replacement no longer exists.']);
            }
        }

        Storage::disk('local')->put($base.'/upload-metadata.json', json_encode([
            'sha256' => $hash,
            'bytes' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => (int) $request->user()->id,
            'uploaded_at' => now()->toIso8601String(),
            'source_url' => 'internal://exhibitions/ve-03/source/'.$hash,
            'creator_name' => 'Platform administrator',
            'license_name' => 'Internal approved asset',
            'license_url' => 'internal://exhibitions/ve-03/license',
            'attribution_text' => 'Internal platform asset.',
            'commercial_use_allowed' => true,
            'runtime_derivative_allowed' => true,
            'status' => 'quarantined_not_processed',
            'hall_mode' => $replacement ? 'replacement' : 'new',
            'replacement_environment_id' => $replacement ? (int) $replacement->environment_id : null,
            'replacement_version_public_id' => $replacement ? (string) $replacement->public_id : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        try {
            $stager->stage(
                $request->file('build_bundle'),
                $hash,
                (int) $request->user()->id,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['build_bundle' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.exhibitions.virtual.ve03.assets.index')
            ->with('status', 'The GLB source and processed build passed validation and are ready to import.');
    }

    public function uploadBuild(Request $request, Ve03ProcessedBuildStager $stager): RedirectResponse
    {
        $validated = $request->validate([
            'source_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'build_bundle' => ['required', 'file', 'max:204800'],
        ]);

        try {
            $stager->stage(
                $request->file('build_bundle'),
                (string) $validated['source_hash'],
                (int) $request->user()->id,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['build_bundle' => $exception->getMessage()]);
        }

        return back()->with('status', 'The processed build passed preflight and is ready to import.');
    }

    public function importBuild(Request $request, Ve03AssetVersionImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'source_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ]);
        $sourceHash = strtolower((string) $validated['source_hash']);
        $base = 'exhibitions/ve-03/quarantine/uploads/'.$sourceHash;
        $disk = Storage::disk('local');
        $metadataPath = $base.'/upload-metadata.json';
        if (! $disk->exists($metadataPath)) {
            return back()->withErrors(['source_hash' => 'The selected GLB source metadata was not found.']);
        }

        $metadata = json_decode((string) $disk->get($metadataPath), true) ?: [];
        $buildPath = (string) ($metadata['staged_build_path'] ?? '');
        if ($buildPath === '' || ! str_starts_with($buildPath, $base.'/builds/')) {
            return back()->withErrors(['source_hash' => 'Upload and validate the processed build before importing rooms.']);
        }

        try {
            $version = $importer->import(
                $disk->path($base.'/source/source.glb'),
                $disk->path($buildPath),
                (int) $request->user()->id,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['source_hash' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'source_hash' => 'The hall could not be imported. Its files remain private; verify the package and try again.',
            ]);
        }

        $metadata = array_replace($metadata, [
            'status' => 'imported',
            'imported_by' => (int) $request->user()->id,
            'imported_at' => now()->toIso8601String(),
            'imported_version_public_id' => (string) ($version['public_id'] ?? ''),
        ]);
        $disk->put($metadataPath, json_encode(
            $metadata,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return redirect()->route('admin.plugins.exhibitions.virtual.rooms.index')
            ->with('status', 'The 3D hall was created and is ready for preview, editing, or publication.');
    }

    public function publish(Request $request, string $version, Ve03AssetVersionImporter $importer): RedirectResponse
    {
        $record = DB::table('exhibition_virtual_environment_versions')->where('public_id', $version)->first();
        abort_unless($record, 404);
        $importer->publish((int) $record->id, (int) $request->user()->id);

        return back()->with('status', 'The immutable 3D room version is now active. Existing room data remains unchanged.');
    }

    public function preview(Request $request, string $version): Response
    {
        $record = $this->previewVersion($version);
        $transcoderUrl = route('exhibitions.virtual.ve03.transcoder', 'basis_transcoder.js');
        $previewBackUrl = match ($request->query('return')) {
            'halls' => route('admin.plugins.exhibitions.virtual.rooms.index'),
            'edit' => route('admin.plugins.exhibitions.virtual.ve03.assets.edit', $record->public_id),
            default => route('admin.plugins.exhibitions.virtual.ve03.assets.index'),
        };

        return response()->view('exhibitions::public.ve03-room', [
            'room' => (object) [
                'public_id' => (string) $record->public_id,
                'slug' => 'preview-'.$record->public_id,
                'title' => (string) $record->environment_title,
                'short_description' => 'Private live preview of the imported hall before publication.',
            ],
            'placements' => collect(),
            've03' => null,
            'returnContext' => null,
            'isAdminPreview' => true,
            'previewBackUrl' => $previewBackUrl,
            'viewerConfigOverride' => [
                'manifest_url' => route('admin.plugins.exhibitions.virtual.ve03.assets.preview-manifest', $record->public_id),
                'telemetry_url' => null,
                'availability_url' => null,
                'transcoder_base_url' => substr($transcoderUrl, 0, -strlen('basis_transcoder.js')),
                'csrf_token' => csrf_token(),
                'cart_summary_url' => null,
                'cart_add_url' => null,
                'cart_remove_url' => null,
                'checkout_start_url' => null,
                'return_context' => null,
                'availability_poll_seconds' => 0,
                'realtime' => ['enabled' => false],
            ],
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function edit(string $version): Response
    {
        $record = DB::table('exhibition_virtual_environment_versions as versions')
            ->join('exhibition_virtual_environments as environments', 'environments.id', '=', 'versions.environment_id')
            ->where('versions.public_id', $version)
            ->select([
                'versions.*',
                'environments.title as environment_title',
            ])
            ->first();
        abort_unless($record, 404);

        $rooms = DB::table('exhibition_virtual_environment_rooms')
            ->where('version_id', $record->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->view('exhibitions::admin.virtual.ve03-hall-edit', [
            'version' => $record,
            'rooms' => $rooms,
        ]);
    }

    public function update(Request $request, string $version, Ve03HallManager $halls): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'room_titles' => ['sometimes', 'array'],
            'room_titles.*' => ['required', 'string', 'max:180'],
        ]);

        try {
            $halls->updatePresentation(
                $version,
                (int) $request->user()->id,
                (string) $validated['title'],
                is_array($validated['room_titles'] ?? null) ? $validated['room_titles'] : [],
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['hall' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.exhibitions.virtual.ve03.assets.edit', $version)
            ->with('status', 'Hall name and room labels were updated. The validated 3D runtime remains unchanged.');
    }

    public function previewManifest(string $version): JsonResponse
    {
        $record = $this->previewVersion($version);
        $metadata = json_decode((string) $record->metadata_report, true) ?: [];
        $spawn = $metadata['spawns'][0] ?? null;
        abort_unless(is_array($spawn) && count($spawn['translation'] ?? []) === 3, 404);

        $rooms = DB::table('exhibition_virtual_environment_rooms')
            ->where('version_id', $record->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (object $room): array => [
                'code' => (string) $room->room_code,
                'title' => (string) $room->title,
                'sort_order' => (int) $room->sort_order,
                'bounds' => json_decode((string) $room->bounds, true) ?: [],
            ])->all();
        abort_unless(count($rooms) >= 2, 404);

        $safetyDisplays = DB::table('exhibition_virtual_environment_slots')
            ->where('version_id', $record->id)
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
                    'position' => array_map('floatval', json_decode((string) $slot->translation, true) ?: []),
                    'quaternion' => array_map('floatval', json_decode((string) $slot->rotation, true) ?: []),
                    'outward_offset_m' => 0.035,
                ],
            ])->all();

        $runtimeHash = strtolower((string) $record->runtime_sha256);
        $manifest = [
            'schema_version' => 1,
            'exhibition' => [
                'id' => (string) $record->environment_public_id,
                'slug' => 'preview-'.$record->public_id,
                'title' => (string) $record->environment_title,
                'version' => (int) $record->version_number,
                'preview' => true,
            ],
            'environment' => [
                'version_id' => (string) $record->public_id,
                'url' => route('admin.plugins.exhibitions.virtual.ve03.assets.asset', [$record->public_id, 'environment']),
                'collision_url' => route('admin.plugins.exhibitions.virtual.ve03.assets.asset', [$record->public_id, 'collision']),
                'hash' => 'sha256-'.$runtimeHash,
                'bytes' => (int) $record->runtime_size,
                'collision_bytes' => (int) $record->collision_size,
                'compression' => 'meshopt',
                'texture_format' => 'ktx2',
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
            'safety_displays' => $safetyDisplays,
            'placements' => [],
            'availability_version' => 0,
        ];

        return response()->json($manifest, 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function duplicate(Request $request, string $version, Ve03HallManager $halls): RedirectResponse
    {
        try {
            $copy = $halls->duplicate($version, (int) $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['hall' => $exception->getMessage()]);
        }

        return back()->with('status', 'Hall duplicated as draft version v'.((int) ($copy['version_number'] ?? 0)).'. Review it before publishing.');
    }

    public function destroy(Request $request, string $version, Ve03HallManager $halls): RedirectResponse
    {
        try {
            $halls->delete($version, (int) $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['hall' => $exception->getMessage()]);
        }

        $redirect = DB::table('exhibition_virtual_environment_versions')->exists()
            ? redirect()->route('admin.plugins.exhibitions.virtual.rooms.index')
            : redirect()->route('admin.plugins.exhibitions.virtual.ve03.assets.index', ['new' => 1]);

        return $redirect->with('status', 'The selected 3D hall was deleted.');
    }

    public function asset(string $version, string $asset): BinaryFileResponse
    {
        abort_unless(in_array($asset, ['environment', 'collision'], true), 404);
        $record = DB::table('exhibition_virtual_environment_versions')->where('public_id', $version)->first();
        abort_unless($record, 404);
        $pathColumn = $asset === 'environment' ? 'runtime_path' : 'collision_path';
        $hashColumn = $asset === 'environment' ? 'runtime_sha256' : 'collision_sha256';
        $path = Storage::disk('local')->path((string) $record->{$pathColumn});
        $actual = realpath($path);
        $trusted = realpath(Storage::disk('local')->path('exhibitions/ve-03/runtime'));
        abort_unless(is_string($actual) && is_file($actual) && is_string($trusted), 404);
        abort_unless(str_starts_with(strtolower(str_replace('\\', '/', $actual)), rtrim(strtolower(str_replace('\\', '/', $trusted)), '/').'/'), 404);
        abort_unless(hash_equals(strtolower((string) $record->{$hashColumn}), strtolower((string) hash_file('sha256', $actual))), 503);

        return response()->file($actual, [
            'Content-Type' => 'model/gltf-binary',
            'Content-Disposition' => 'inline; filename="'.$asset.'.glb"',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function versions(): \Illuminate\Support\Collection
    {
        return DB::table('exhibition_virtual_environment_versions as versions')
            ->join('exhibition_virtual_environments as environments', 'environments.id', '=', 'versions.environment_id')
            ->select([
                'versions.*',
                'environments.code as environment_code',
                'environments.title as environment_title',
                DB::raw('(select count(*) from exhibition_virtual_environment_rooms where version_id = versions.id) as room_count'),
                DB::raw('(select count(*) from exhibition_virtual_environment_slots where version_id = versions.id) as slot_count'),
            ])
            ->orderByDesc('versions.id')
            ->get()
            ->map(function (object $version): object {
                $version->stats = json_decode((string) $version->stats_report, true) ?: [];
                $version->validation = json_decode((string) $version->validation_report, true) ?: [];
                $version->compatibility = $version->compatibility_report
                    ? (json_decode((string) $version->compatibility_report, true) ?: [])
                    : [];

                return $version;
            });
    }

    private function previewVersion(string $version): object
    {
        $record = DB::table('exhibition_virtual_environment_versions as versions')
            ->join('exhibition_virtual_environments as environments', 'environments.id', '=', 'versions.environment_id')
            ->where('versions.public_id', $version)
            ->select([
                'versions.*',
                'environments.public_id as environment_public_id',
                'environments.title as environment_title',
            ])
            ->first();
        abort_unless($record && in_array($record->status, ['ready', 'published', 'superseded'], true), 404);

        return $record;
    }
}
