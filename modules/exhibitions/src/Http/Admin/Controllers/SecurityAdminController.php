<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Exhibition\Application\Security\ExhibitionSecurityPosture;

final class SecurityAdminController extends Controller
{
    public function index(ExhibitionSecurityPosture $security): View
    {
        return view('exhibitions::admin.security.index', ['permissions' => $security->permissionMatrix(), 'privateAssets' => $security->privateAssets(), 'auditCoverage' => $security->auditCoverage(), 'guarantees' => $security->guarantees()]);
    }
}
