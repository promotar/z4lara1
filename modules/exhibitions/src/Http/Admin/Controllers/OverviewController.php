<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Exhibition\Application\Admin\ExhibitionAdminService;
use Modules\Exhibition\Contracts\Readiness\ExhibitionReadiness;

final class OverviewController extends Controller
{
    public function index(Request $request, ExhibitionReadiness $readiness, ExhibitionAdminService $admin): View
    {
        return view('exhibitions::admin.overview', [
            'readiness' => $readiness->status(),
            'metrics' => $admin->metrics($request->user()),
        ]);
    }

    public function health(ExhibitionReadiness $readiness): JsonResponse
    {
        $status = $readiness->status();

        return response()->json([
            'module' => 'exhibitions',
            'version' => $status->version,
            'available' => $status->available,
            'checks' => [
                'artist_identity_table' => $status->artistIdentityTableAvailable,
                'audit_table' => $status->auditTableAvailable,
                'core_identity_external' => $status->coreIdentityIsExternal,
                'store_gateway_bound' => $status->storeGatewayBound,
            ],
        ], $status->available ? 200 : 503);
    }
}
