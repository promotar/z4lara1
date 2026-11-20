<?php

namespace Modules\GoogleLogin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\GoogleLogin\Services\GoogleLoginSettings;

class GoogleLoginSettingsController extends Controller
{
    public function edit(GoogleLoginSettings $settings): View
    {
        return view('google-login::admin.settings', [
            'settings' => $settings->publicValues(),
            'callbackUrl' => route('google-login.auth.callback'),
        ]);
    }

    public function update(Request $request, GoogleLoginSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'allow_registration' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
            'clear_client_secret' => ['nullable', 'boolean'],
            'hosted_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
            ],
            'button_text' => ['required', 'string', 'max:80', Rule::notIn(['0'])],
        ]);

        $settings->update($validated + [
            'enabled' => $request->boolean('enabled'),
            'allow_registration' => $request->boolean('allow_registration'),
            'clear_client_secret' => $request->boolean('clear_client_secret'),
        ]);

        return back()->with('status', 'Google Login settings saved securely.');
    }
}
