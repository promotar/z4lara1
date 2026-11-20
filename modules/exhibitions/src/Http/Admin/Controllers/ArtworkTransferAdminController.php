<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Admin\AdminArtworkService;
use Modules\Exhibition\Application\Admin\ArtworkCsvService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkTransferAdminController extends Controller
{
    public function __construct(
        private readonly ArtworkCsvService $csv,
        private readonly AdminArtworkService $artworks,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            foreach ($this->csv->exportRows() as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, 'exhibitions-artworks-'.now()->format('Ymd-His').'.csv', PrivateResponseHeaders::for('text/csv; charset=UTF-8'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);

        return view('exhibitions::admin.artworks.import', [
            'artists' => $this->artworks->activeOwners(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'fallback_artist_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        try {
            $result = $this->csv->import(
                $validated['file'],
                (int) $request->user()->id,
                filled($validated['fallback_artist_user_id'] ?? null) ? (int) $validated['fallback_artist_user_id'] : null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf(
            'Import finished: %d read, %d created, %d updated, %d image(s), %d failed.',
            $result['read'], $result['created'], $result['updated'], $result['images'], $result['failed'],
        ))->with('import_report', $result);
    }
}
