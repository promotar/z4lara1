<?php

namespace App\Http\Controllers;

use App\Platform\Core\Accounts\AccountSectionRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __invoke(Request $request, AccountSectionRegistry $sections): View
    {
        $user = $request->user();
        $user->loadMissing('roles');

        return view('frontend.account', [
            'user' => $user,
            'sections' => $sections->sections($user),
            'canAccessDashboard' => $user->hasAnyRole(['super-admin', 'admin', 'staff', 'employee'])
                || $user->getAllPermissions()->isNotEmpty(),
        ]);
    }
}
