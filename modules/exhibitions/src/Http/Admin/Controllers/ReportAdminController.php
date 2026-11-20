<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Exhibition\Application\Reports\ExhibitionReportService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportAdminController extends Controller
{
    public function __construct(private readonly ExhibitionReportService $reports) {}

    public function index(Request $request, string $section = 'artists'): View
    {
        abort_unless($request->user()->can('exhibitions.reports'), 403);

        $sections = collect(ExhibitionReportService::SECTIONS)
            ->map(static fn (string $label): string => __($label))
            ->all();

        return view('exhibitions::admin.reports.index', $this->reports->report($section, $request->only(['from', 'to', 'status', 'currency'])) + ['sections' => $sections]);
    }

    public function export(Request $request, string $section): StreamedResponse
    {
        abort_unless($request->user()->can('exhibitions.reports'), 403);
        $rows = $this->reports->csv($section, $request->only(['from', 'to', 'status', 'currency']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }fclose($out);
        }, 'exhibitions-'.$section.'-'.now()->format('Ymd-His').'.csv', PrivateResponseHeaders::for('text/csv; charset=UTF-8'));
    }
}
