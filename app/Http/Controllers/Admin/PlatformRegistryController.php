<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Logs\LiveLogReader;
use App\Platform\Core\Logs\PlatformLogManager;
use App\Platform\Core\Registry\PlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class PlatformRegistryController extends Controller
{
    public function index(Request $request, PlatformRegistry $registry, PlatformLogManager $logs): View
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return view('admin.platform-registry.index', [
            'functions' => $registry->functions(),
            'hooks' => $registry->hooks(),
            'routes' => $registry->routes(),
            'unregisteredRoutes' => $registry->unregisteredRoutes(),
            'activeTab' => $this->activeTab($request),
            'reports' => $this->reportSummaries(),
            'selectedReport' => $this->selectedReport($request),
            'successLogs' => $logs->recentSuccess(),
            'errorLogs' => $logs->recentErrors(),
            'liveLogUrl' => route('admin.platform-registry.live-log'),
        ]);
    }

    public function liveLog(Request $request, LiveLogReader $logs): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $limit = min(500, max(1, (int) $request->query('limit', 500)));

        return response()->json($logs->latest($limit));
    }

    /**
     * @return array<string, string>|null
     */
    private function selectedReport(Request $request): ?array
    {
        $name = basename((string) $request->query('report', ''));

        if ($name === '' || ! str_ends_with($name, '.md')) {
            return null;
        }

        $directory = base_path('docs/project-management/implementation-reports');
        $path = $directory.DIRECTORY_SEPARATOR.$name;

        if (! File::isFile($path) || ! $this->isSafeReportPath($path)) {
            return null;
        }

        return [
            'name' => $name,
            'content' => File::get($path),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
            ])
            ->values()
            ->all();
    }

    private function activeTab(Request $request): string
    {
        $tab = (string) $request->query('tab', '');

        return in_array($tab, ['functions', 'hooks', 'routes', 'reports', 'success', 'errors', 'live-log'], true)
            ? $tab
            : 'functions';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    private function isSafeReportPath(string $path): bool
    {
        $base = realpath(base_path('docs/project-management/implementation-reports'));
        $resolved = realpath($path);

        return is_string($base) && is_string($resolved) && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR);
    }
}
