<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Backups\BackupManager;
use App\Platform\Core\Models\BackupCheckpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return view('admin.backups.index', [
            'backups' => $this->backupRecords(),
        ]);
    }

    public function store(Request $request, BackupManager $backups): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $checkpoint = $backups->createCheckpoint(
            'platform.manual_backup',
            'platform',
            'manual',
            [
                'requested_from' => 'system_backup',
                'requested_by' => $request->user()?->email,
                'created_from_ip' => $request->ip(),
            ],
            'manual',
            $request->user()?->id,
        );

        $backups->addRestoreNote($checkpoint, 'Manual super-admin checkpoint created from System Backup.');
        $backups->markCheckpointCompleted($checkpoint);
        $this->pruneOldBackups();

        return redirect()
            ->route('admin.backups.index')
            ->with('status', 'Manual backup checkpoint created successfully.');
    }

    public function showLocation(Request $request, BackupCheckpoint $backup): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        if (! $this->isSafeBackupPath($backup->path)) {
            return redirect()
                ->route('admin.backups.index')
                ->with('status', 'Backup location is not available.');
        }

        return redirect()->away($this->fileManagerUrl(dirname((string) $backup->path)));
    }

    public function restore(Request $request, BackupCheckpoint $backup, BackupManager $backups): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        if (! $this->isSafeBackupPath($backup->path) || ! File::exists((string) $backup->path)) {
            return redirect()
                ->route('admin.backups.index')
                ->with('status', 'Restore was not started because the checkpoint file is missing or outside the approved backup path.');
        }

        $backups->addRestoreNote(
            $backup,
            'Restore requested from System Backup by '.$request->user()->email.' at '.now()->toDateTimeString().'. Automatic restore is not executed for metadata checkpoints; review this checkpoint before applying data changes.',
        );

        return redirect()
            ->route('admin.backups.index')
            ->with('status', 'Backup restore confirmed successfully. Restore note was recorded for this checkpoint.');
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
            ->with('status', 'Backup checkpoint removed successfully.');
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
            ->map(fn (BackupCheckpoint $backup): array => [
                'id' => $backup->id,
                'description' => $this->backupDescription($backup),
                'checkpoint_label' => $this->checkpointLabel((string) $backup->checkpoint_type),
                'status' => $backup->status,
                'created_at' => $backup->created_at?->format('Y-m-d H:i:s') ?? '',
                'path' => $backup->path,
                'file_exists' => $this->isSafeBackupPath($backup->path) && File::exists((string) $backup->path),
                'notes' => $backup->notes,
            ])
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
