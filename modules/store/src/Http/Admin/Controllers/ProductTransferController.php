<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Store\Application\CatalogTransfer\ProductCatalogCsvExporter;
use Modules\Store\Application\CatalogTransfer\ProductCatalogCsvImporter;
use Modules\Store\Domain\Shared\ActorContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductTransferController extends Controller
{
    public function create(): View
    {
        return view('store::admin.products.import');
    }

    public function export(ProductCatalogCsvExporter $exporter): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($exporter): void {
                $stream = fopen('php://output', 'wb');
                if ($stream === false) {
                    return;
                }
                try {
                    $exporter->write($stream);
                } finally {
                    fclose($stream);
                }
            },
            'store-products-'.now()->format('Y-m-d-His').'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function store(Request $request, ProductCatalogCsvImporter $importer): RedirectResponse
    {
        $maximumKilobytes = (int) ceil(((int) config('store.catalog_transfer.maximum_csv_bytes', 20_971_520)) / 1024);
        $data = $request->validate([
            'catalog_csv' => ['required', 'file', 'max:'.$maximumKilobytes],
            'existing_behavior' => ['required', 'in:skip,update'],
            'download_images' => ['nullable', 'boolean'],
            'preserve_status' => ['nullable', 'boolean'],
        ]);
        if ($data['existing_behavior'] === 'update') {
            abort_unless($request->user()?->can('store.products.update'), 403);
        }
        $file = $request->file('catalog_csv');
        if ($file === null || ! in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return back()->withErrors(['catalog_csv' => 'Upload a CSV file exported by the Store plugin.'])->withInput();
        }

        try {
            $result = $importer->import(
                $file->getRealPath(),
                $data['existing_behavior'],
                $request->boolean('download_images'),
                $request->boolean('preserve_status'),
                new ActorContext('user', (string) $request->user()->getAuthIdentifier(), trim((string) $request->user()->name) ?: null),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['catalog_csv' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.plugins.store.products.import.create')
            ->with('success', 'Product catalog import completed.')
            ->with('catalog_import_summary', $result->toArray());
    }
}
