<?php

namespace Modules\GoogleLogin\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Platform\Core\Services\SafeReturnPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\GoogleLogin\Services\GoogleAccountManager;
use Modules\GoogleLogin\Services\GoogleLoginException;
use Modules\GoogleLogin\Services\GoogleOAuthClient;
use Throwable;

class GoogleLoginController extends Controller
{
    public function redirect(Request $request, GoogleOAuthClient $google, SafeReturnPath $returnPath): RedirectResponse
    {
        try {
            $request->session()->put(
                'google_login.return_to',
                $returnPath->sanitize($request->query('return_to')),
            );

            return redirect()->away($google->authorizationUrl($request));
        } catch (GoogleLoginException $exception) {
            return redirect()->route('login')->with('google_login_error', $exception->getMessage());
        }
    }

    public function callback(
        Request $request,
        GoogleOAuthClient $google,
        GoogleAccountManager $accounts,
        SafeReturnPath $returnPath,
    ): RedirectResponse {
        try {
            $user = $accounts->resolve($google->verifiedProfile($request));
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            $destination = $returnPath->sanitize($request->session()->pull('google_login.return_to'))
                ?? route('front.account', absolute: false);

            return redirect()->to($destination);
        } catch (GoogleLoginException $exception) {
            return redirect()->route('login')->with('google_login_error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->with('google_login_error', 'Google sign-in could not be completed. Please try again or use email and password.');
        }
    }
}
