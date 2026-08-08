<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $redirectTo = $request->user()->hasAnyRole(['super-admin', 'admin', 'staff', 'employee'])
            ? route('dashboard', absolute: false)
            : route('front.account', absolute: false);

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($redirectTo.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended($redirectTo.'?verified=1');
    }
}
