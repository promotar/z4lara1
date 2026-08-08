<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Logs\PlatformLogManager;
use App\Platform\Core\Registry\PlatformRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function index(PlatformRegistry $registry, PlatformLogManager $logs): View
    {
        return view('admin.documentation.index', [
            'tasks' => DB::table('documentation_tasks')
                ->orderByRaw('completed_at IS NOT NULL')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'operationLogs' => $this->operationLogs(),
            'functions' => $registry->functions(),
            'hooks' => $registry->hooks(),
            'routes' => $registry->routes(),
            'unregisteredRoutes' => $registry->unregisteredRoutes(),
            'reports' => $this->reportSummaries(),
            'successLogs' => $logs->recentSuccess(40),
            'errorLogs' => $logs->recentErrors(80),
            'laravelErrors' => $this->tail(storage_path('logs/laravel.log'), 80),
            'manifestExample' => $this->manifestExample(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        $sortOrder = $data['sort_order'] ?? ((int) DB::table('documentation_tasks')->max('sort_order') + 10);

        DB::table('documentation_tasks')->insert([
            'title' => $data['title'],
            'details' => $data['details'] ?? null,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Documentation task created successfully.');
    }

    public function update(Request $request, int $task): RedirectResponse
    {
        abort_unless(DB::table('documentation_tasks')->where('id', $task)->exists(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);

        DB::table('documentation_tasks')->where('id', $task)->update([
            'title' => $data['title'],
            'details' => $data['details'] ?? null,
            'sort_order' => $data['sort_order'],
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Documentation task updated successfully.');
    }

    public function toggle(int $task): RedirectResponse
    {
        $current = DB::table('documentation_tasks')->where('id', $task)->first();
        abort_unless($current, 404);

        DB::table('documentation_tasks')->where('id', $task)->update([
            'completed_at' => $current->completed_at ? null : now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', $current->completed_at ? 'Documentation task reopened successfully.' : 'Documentation task marked as completed.');
    }

    public function destroy(int $task): RedirectResponse
    {
        abort_unless(DB::table('documentation_tasks')->where('id', $task)->exists(), 404);

        DB::table('documentation_tasks')->where('id', $task)->delete();

        return back()->with('status', 'Documentation task deleted successfully.');
    }

    public function viewReport(string $report)
    {
        $path = $this->reportPath($report);

        return response()->file($path, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function downloadReport(string $report)
    {
        return response()->download($this->reportPath($report));
    }

    private function operationLogs(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('operation_logs')) {
            return [];
        }

        return DB::table('operation_logs')
            ->latest('created_at')
            ->latest('id')
            ->limit(120)
            ->get()
            ->all();
    }

    private function reportSummaries(): array
    {
        $directory = base_path('docs/project-management/implementation-reports');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'md')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take(50)
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                'size' => $this->formatBytes($file->getSize()),
                'view_url' => route('admin.documentation.reports.view', $file->getFilename()),
                'download_url' => route('admin.documentation.reports.download', $file->getFilename()),
            ])
            ->values()
            ->all();
    }

    private function reportPath(string $report): string
    {
        $report = trim($report);
        abort_if($report === '' || $report !== basename($report), 404);
        abort_unless(Str::endsWith($report, '.md'), 404);

        $directory = base_path('docs/project-management/implementation-reports');
        $path = $directory.DIRECTORY_SEPARATOR.$report;

        abort_unless(File::isFile($path), 404);

        return $path;
    }

    private function tail(string $path, int $lines): array
    {
        if (! is_file($path)) {
            return [];
        }

        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($content)) {
            return [];
        }

        return array_slice($content, -max(1, $lines));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function manifestExample(): string
    {
        return json_encode([
            'name' => 'Theme Editor',
            'slug' => 'theme-editor',
            'version' => '1.0.0',
            'provider' => 'Modules\\ThemeEditor\\ThemeEditorServiceProvider',
            'provider_file' => 'src/ThemeEditorServiceProvider.php',
            'description' => 'Safe admin theme override editor.',
            'author' => 'Art INPA',
            'routes' => [
                'admin' => [
                    'file' => 'routes/admin.php',
                    'prefix' => 'admin/plugins/theme-editor',
                    'name' => 'admin.plugins.theme-editor.',
                    'middleware' => ['web', 'auth', 'staff', 'permission:theme-editor.manage'],
                ],
            ],
            'permissions' => ['theme-editor.manage'],
            'functions' => [
                'theme-editor.files.browse' => ['description' => 'Browse editable theme files'],
                'theme-editor.overrides.save' => ['description' => 'Create safe theme overrides'],
            ],
            'hooks' => [
                'theme-editor.override.saved' => ['type' => 'action', 'description' => 'After an override is saved'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
