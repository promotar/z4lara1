<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($this->verifiedRedirect($request));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    private function verifiedRedirect(Request $request): string
    {
        return $request->user()->hasAnyRole(['super-admin', 'admin', 'staff', 'employee'])
            ? route('dashboard', absolute: false)
            : route('front.account', absolute: false);
    }
}
