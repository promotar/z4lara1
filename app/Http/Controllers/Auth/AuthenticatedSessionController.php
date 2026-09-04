<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Platform\Core\Services\SafeReturnPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, SafeReturnPath $returnPath): View
    {
        return view('auth.login', [
            'returnTo' => $returnPath->sanitize($request->query('return_to')),
        ]);
    }

    public function store(LoginRequest $request, SafeReturnPath $returnPath): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->to(
            $returnPath->sanitize($request->input('return_to'))
                ?? route('front.account', absolute: false)
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
