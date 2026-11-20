<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Store\Contracts\Readiness\StoreReadiness;

final class OverviewController extends Controller
{
    public function __invoke(StoreReadiness $readiness): View
    {
        return view('store::admin.overview', [
            'store' => $readiness->status(),
        ]);
    }
}
