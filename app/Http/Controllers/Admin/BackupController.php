<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Backups\BackupManager;
use App\Platform\Core\Backups\SystemBackup;
use App\Platform\Core\Models\BackupCheckpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return view('admin.backups.index', [
            'backups' => $this->backupRecords(),
            'liveDatabase' => $this->liveDatabaseStats(),
        ]);
    }

    public function store(Request $request, BackupManager $backups, SystemBackup $system): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        try {
            $result = $system->create('manual');
        } catch (\Throwable $exception) {
            report($exception);

            $failed = $backups->createCheckpoint(
                'platform.manual_backup',
                'platform',
                'manual',
                [
                    'requested_from' => 'system_backup',
                    'requested_by' => $request->user()?->email,
                    'error' => $exception->getMessage(),
                ],
                'manual',
                $request->user()?->id,
            );
            $backups->markCheckpointFailed($failed);

            return redirect()
                ->route('admin.backups.index')
                ->with('status', __('core.backups.flash.failed').' '.$exception->getMessage());
        }

        $checkpoint = $backups->createCheckpoint(
            'platform.manual_backup',
            'platform',
            'manual',
            [
                'requested_from' => 'system_backup',
                'requested_by' => $request->user()?->email,
                'created_from_ip' => $request->ip(),
                'database_dump' => $result['db'],
                'database_size' => $result['db_size'],
                'code_archive' => $result['code'],
                'code_size' => $result['code_size'],
                'code_files' => $result['files'],
                'database_tables' => $result['tables'],
            ],
            'manual',
            $request->user()?->id,
        );

        $backups->addRestoreNote($checkpoint, sprintf(
            'Full database dump (%s) + code-only archive (%s, %d files). Media, uploads and private documents are excluded by design.',
            $this->humanSize($result['db_size']),
            $this->humanSize($result['code_size']),
            $result['files'],
        ));
        $backups->markCheckpointCompleted($checkpoint);
        $this->pruneOldBackups();

        return redirect()
            ->route('admin.backups.index')
            ->with('status', __('core.backups.flash.created').' '.$this->humanSize($result['db_size']).' + '.$this->humanSize($result['code_size']));
    }

    /**
     * Download one file of a backup (database dump or code archive).
     */
    public function download(Request $request, BackupCheckpoint $backup, string $type): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        abort_unless(in_array($type, ['db', 'code'], true), 404);

        $meta = (array) ($backup->metadata ?? []);
        $path = $type === 'db' ? ($meta['database_dump'] ?? null) : ($meta['code_archive'] ?? null);

        abort_unless(is_string($path) && $this->isSafeBackupFile($path), 404);

        return response()->download($path, basename($path), [
            'Content-Type' => $type === 'db' ? 'application/gzip' : 'application/zip',
        ]);
    }

    /**
     * @return array{size: string, tables: int}
     */
    private function liveDatabaseStats(): array
    {
        try {
            $row = DB::selectOne(
                'select round(sum(data_length + index_length) / 1024 / 1024, 1) as mb, count(*) as tables
                 from information_schema.tables where table_schema = database() and table_type = "BASE TABLE"'
            );
        } catch (\Throwable) {
            return ['size' => '—', 'tables' => 0];
        }

        return [
            'size' => number_format((float) ($row->mb ?? 0), 1).' MB',
            'tables' => (int) ($row->tables ?? 0),
        ];
    }

    private function isSafeBackupFile(?string $path): bool
    {
        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        $base = realpath(storage_path('app/backups'));
        $real = realpath($path);

        return is_string($base)
            && is_string($real)
            && str_starts_with($real, $base.DIRECTORY_SEPARATOR)
            && is_file($real);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function showLocation(Request $request, BackupCheckpoint $backup): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        if (! $this->isSafeBackupPath($backup->path)) {
            return redirect()
                ->route('admin.backups.index')
                ->with('status', __('core.backups.flash.location_unavailable'));
        }

        return redirect()->away($this->fileManagerUrl(dirname((string) $backup->path)));
    }

    public function restore(Request $request, BackupCheckpoint $backup, BackupManager $backups): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        if (! $this->isSafeBackupPath($backup->path) || ! File::exists((string) $backup->path)) {
            return redirect()
                ->route('admin.backups.index')
                ->with('status', __('core.backups.flash.restore_blocked'));
        }

        $backups->addRestoreNote(
            $backup,
            'Restore requested from System Backup by '.$request->user()->email.' at '.now()->toDateTimeString().'. Automatic restore is not executed for metadata checkpoints; review this checkpoint before applying data changes.',
        );

        return redirect()
            ->route('admin.backups.index')
            ->with('status', __('core.backups.flash.restore_confirmed'));
    }

    public function destroy(Request $request, BackupCheckpoint $backup): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $path = (string) $backup->path;

        if ($this->isSafeBackupPath($path) && File::exists($path)) {
            File::delete($path);
        }

        $backup->delete();

        return redirect()
            ->route('admin.backups.index')
            ->with('status', __('core.backups.flash.deleted'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function backupRecords(): array
    {
        return BackupCheckpoint::query()
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (BackupCheckpoint $backup): array {
                $meta = (array) ($backup->metadata ?? []);
                $dbPath = (string) ($meta['database_dump'] ?? '');
                $codePath = (string) ($meta['code_archive'] ?? '');

                return [
                    'id' => $backup->id,
                    'description' => $this->backupDescription($backup),
                    'checkpoint_label' => $this->checkpointLabel((string) $backup->checkpoint_type),
                    'status' => $backup->status,
                    'created_at' => $backup->created_at?->format('Y-m-d H:i:s') ?? '',
                    'path' => $backup->path,
                    'file_exists' => $this->isSafeBackupPath($backup->path) && File::exists((string) $backup->path),
                    'notes' => $backup->notes,
                    'error' => (string) ($meta['error'] ?? ''),
                    'database' => [
                        'exists' => $this->isSafeBackupFile($dbPath),
                        'size' => $this->humanSize((int) ($meta['database_size'] ?? 0)),
                        'tables' => (int) ($meta['database_tables'] ?? 0),
                    ],
                    'code' => [
                        'exists' => $this->isSafeBackupFile($codePath),
                        'size' => $this->humanSize((int) ($meta['code_size'] ?? 0)),
                        'files' => (int) ($meta['code_files'] ?? 0),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function pruneOldBackups(): void
    {
        BackupCheckpoint::query()
            ->latest('created_at')
            ->latest('id')
            ->skip(10)
            ->take(1000)
            ->get()
            ->each(function (BackupCheckpoint $backup): void {
                $path = (string) $backup->path;

                if ($this->isSafeBackupPath($path) && File::exists($path)) {
                    File::delete($path);
                }

                $backup->delete();
            });
    }

    private function backupDescription(BackupCheckpoint $backup): string
    {
        $targetSlug = $backup->target_slug ?: 'platform';

        return match ($backup->operation_type) {
            'platform.manual_backup' => 'Manual platform backup',
            'platform.verification' => 'Backup system verification',
            'plugin.upload.install', 'plugin.install' => 'Plugin install backup for '.$targetSlug,
            'plugin.activate' => 'Plugin activation backup for '.$targetSlug,
            'plugin.disable' => 'Plugin disable backup for '.$targetSlug,
            'plugin.uninstall' => 'Plugin uninstall backup for '.$targetSlug,
            'plugin.update' => 'Plugin update backup for '.$targetSlug,
            'theme.update' => 'Theme update backup for '.$targetSlug,
            default => ucfirst(str_replace(['.', '_'], ' ', $backup->operation_type)).' backup',
        };
    }

    private function checkpointLabel(string $type): string
    {
        return match ($type) {
            'manual' => 'Manual backup',
            'verification' => 'System check',
            'step-checkpoint' => 'Automatic step backup',
            'metadata' => 'Metadata backup',
            'plugin-install' => 'Before plugin install',
            'plugin-uninstall' => 'Before plugin uninstall',
            'plugin-update' => 'Before plugin update',
            'theme-update' => 'Before theme update',
            default => ucfirst(str_replace(['-', '_'], ' ', $type)),
        };
    }

    private function fileManagerUrl(string $folder): string
    {
        $baseUrl = rtrim((string) config('platform.backup_file_manager_url', ''), '/');

        if ($baseUrl === '') {
            return route('admin.backups.index');
        }

        $path = collect(explode('/', str_replace('\\', '/', $folder)))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');

        return $baseUrl.'/files/'.$path;
    }

    private function isSafeBackupPath(?string $path): bool
    {
        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        $base = realpath(storage_path('app/platform'));
        $resolved = realpath($path) ?: $path;

        return is_string($base) && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR);
    }
}
