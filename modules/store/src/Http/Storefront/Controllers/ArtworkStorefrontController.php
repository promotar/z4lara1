<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Modules\Store\Application\Storefront\StorefrontLocale;

final class ArtworkStorefrontController extends Controller
{
    public function __construct(
        private readonly StorefrontLocale $locale,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->locale->apply();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name,name_asc,name_desc,relevance'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (Route::has('exhibitions.artworks.index')) {
            return redirect()->route('exhibitions.artworks.index', array_filter([
                'q' => $filters['q'] ?? null,
                'category' => $filters['category'] ?? null,
                'page' => $filters['page'] ?? null,
            ]));
        }

        return redirect()->route('store.index', ['section' => 'artworks']);
    }
}
