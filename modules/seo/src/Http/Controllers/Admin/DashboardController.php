<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Seo\Models\SeoAuditIssue;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Models\SeoSitemapRun;
use Modules\Seo\Services\SeoContentTypeRegistry;
use Modules\Seo\Services\SeoSiteResolver;
use Throwable;

final class DashboardController extends Controller
{
    public function __invoke(SeoSiteResolver $sites, SeoContentTypeRegistry $types): View
    {
        $counts = [
            'indexable' => 0,
            'active_redirects' => 0,
            'open_issues' => 0,
        ];
        $sitemap = [
            'enabled' => true,
            'status' => 'never_generated',
            'urls' => 0,
            'last_generated' => null,
        ];
        $issueSummary = collect();

        try {
            $siteId = $sites->id();
            $counts['indexable'] = $types->indexableCount();
            $counts['active_redirects'] = SeoRedirect::query()
                ->where('site_id', $siteId)
                ->where('is_active', true)
                ->count();
            $counts['open_issues'] = SeoAuditIssue::query()
                ->where('site_id', $siteId)
                ->where('status', 'open')
                ->count();

            $sitemap['enabled'] = (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'enabled', '', true);
            $run = SeoSitemapRun::query()
                ->where('site_id', $siteId)
                ->latest('id')
                ->first();

            if ($run !== null) {
                $sitemap['status'] = (string) $run->status;
                $sitemap['urls'] = (int) $run->urls_count;
                $sitemap['last_generated'] = $run->completed_at;
            }

            $issueSummary = SeoAuditIssue::query()
                ->where('site_id', $siteId)
                ->where('status', 'open')
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->orderByDesc('total')
                ->limit(5)
                ->get();
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('seo::admin.dashboard', compact('counts', 'sitemap', 'issueSummary'));
    }
}
