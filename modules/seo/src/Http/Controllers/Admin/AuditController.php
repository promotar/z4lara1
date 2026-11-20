<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seo\Jobs\RunAuditJob;
use Modules\Seo\Models\SeoAuditIssue;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Services\SeoAuditEngine;
use Modules\Seo\Services\SeoContentTypeRegistry;
use Modules\Seo\Services\SeoSiteResolver;
use Throwable;

final class AuditController extends Controller
{
    public function index(Request $request, SeoSiteResolver $sites, SeoContentTypeRegistry $types): View
    {
        $issues = null;
        $lastRun = null;
        $lastErrors = 0;
        $lastWarnings = 0;
        $lastUrls = 0;
        $issueSummaries = collect();

        $status = trim((string) $request->query('status', 'open'));
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('search', ''));

        try {
            $siteId = $sites->id();
            $lastRun = SeoSetting::valueFor($siteId, 'seo', 'audit', 'last_run', '', null);
            $lastErrors = (int) SeoSetting::valueFor($siteId, 'seo', 'audit', 'last_errors', '', 0);
            $lastWarnings = (int) SeoSetting::valueFor($siteId, 'seo', 'audit', 'last_warnings', '', 0);
            $lastUrls = (int) SeoSetting::valueFor($siteId, 'seo', 'audit', 'last_urls', '', 0);

            $issueSummaries = SeoAuditIssue::query()
                ->where('site_id', $siteId)
                ->where('status', 'open')
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->orderByDesc('total')
                ->get();

            $issues = SeoAuditIssue::query()
                ->where('site_id', $siteId)
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($type !== '', fn ($query) => $query->where('type', $type))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('url', 'like', '%'.$search.'%')
                            ->orWhere('message', 'like', '%'.$search.'%');
                    });
                })
                ->orderByDesc('detected_at')
                ->paginate(25)
                ->withQueryString();
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('seo::admin.audit', [
            'issues' => $issues,
            'issueSummaries' => $issueSummaries,
            'contentTypes' => $types->labels(),
            'lastRun' => $lastRun,
            'lastErrors' => $lastErrors,
            'lastWarnings' => $lastWarnings,
            'runs' => SeoAuditRun::query()->orderByDesc('id')->limit(5)->get(),
            'lastUrls' => $lastUrls,
            'status' => $status,
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function run(SeoSiteResolver $sites, SeoAuditEngine $engine): RedirectResponse
    {
        $started = microtime(true);

        try {
            $siteId = $sites->id();
            $result = $engine->run($siteId, (int) config('seo.audit.max_urls', 100));
            $duration = (int) round((microtime(true) - $started) * 1000);

            SeoAuditRun::query()->create([
                'site_id' => $siteId,
                'status' => 'completed',
                'urls' => (int) ($result['urls'] ?? 0),
                'records' => (int) ($result['records'] ?? 0),
                'errors' => (int) ($result['errors'] ?? 0),
                'warnings' => (int) ($result['warnings'] ?? 0),
                'duration_ms' => $duration,
                'checked_urls' => json_encode($result['checked'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            return back()->with('status', __('seo::ui.audit_completed', [
                'issues' => ($result['errors'] ?? 0) + ($result['warnings'] ?? 0),
            ]));
        } catch (Throwable $exception) {
            report($exception);

            try {
                SeoAuditRun::query()->create([
                    'site_id' => null,
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ]);
            } catch (Throwable) {
                // ignore logging failures
            }

            return back()->withErrors(['audit' => $exception->getMessage()]);
        }
    }

    public function resolve(SeoAuditIssue $issue, SeoSiteResolver $sites): RedirectResponse
    {
        if ((int) $issue->site_id !== $sites->id()) {
            abort(404);
        }

        $issue->fill([
            'status' => 'resolved',
            'resolved_at' => now(),
        ])->save();

        return back()->with('status', __('seo::ui.audit_resolved'));
    }
}
