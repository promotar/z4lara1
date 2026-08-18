<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\s]{5,50}$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $name = trim($validated['first_name'].' '.$validated['last_name']);

        $requiresEmailVerification = filter_var(
            $settings->values()['general.email_verification_required'] ?? true,
            FILTER_VALIDATE_BOOL,
        );

        $user = User::create([
            'name' => $name,
            'email' => $validated['email'],
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'password' => Hash::make($validated['password']),
        ]);

        if (! $requiresEmailVerification) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $defaultRole = $settings->values()['general.default_user_role'] ?? 'user';

        if (Role::where('name', $defaultRole)->exists()) {
            $user->assignRole($defaultRole);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect($requiresEmailVerification
            ? route('verification.notice', absolute: false)
            : route('front.account', absolute: false));
    }
}
