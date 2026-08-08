<?php

namespace App\Http\Controllers;

use App\Installation\InstallationState;
use App\Installation\PlatformInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class InstallationController extends Controller
{
    public function __construct(private readonly InstallationState $state) {}

    public function index(Request $request): RedirectResponse
    {
        if ($this->state->installed()) {
            return redirect('/');
        }

        $request->session()->forget('installation');
        $request->session()->put('installation.mode', 'fresh');

        return redirect()->route('install.platform');
    }

    public function platform(Request $request): View|RedirectResponse
    {
        if ($this->state->installed()) {
            return redirect('/');
        }

        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        return view('installation.wizard', ['step' => 1]);
    }

    public function storePlatform(Request $request): RedirectResponse
    {
        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'domain' => ['required', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);
        $request->session()->put('installation.platform', ['name' => $validated['name'], 'domain' => $validated['domain']]);
        if ($request->hasFile('logo')) {
            $request->session()->put('installation.logo', $request->file('logo')->store('installation', 'local'));
        }

        return redirect()->route('install.database');
    }

    public function database(Request $request): View|RedirectResponse
    {
        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        if (! $request->session()->has('installation.platform')) {
            return redirect()->route('install.platform');
        }
        return view('installation.wizard', ['step' => 2]);
    }

    public function storeDatabase(Request $request, PlatformInstaller $installer): RedirectResponse
    {
        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        $database = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:500'],
            'erase_confirmation' => ['accepted'],
        ]);
        try {
            $installer->testDatabase($database);
        } catch (Throwable $exception) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $exception->getMessage()]);
        }
        $request->session()->put('installation.database', $database);
        return redirect()->route('install.owner');
    }

    public function owner(Request $request): View|RedirectResponse
    {
        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        if (! $request->session()->has('installation.database')) {
            return redirect()->route('install.database');
        }
        return view('installation.wizard', ['step' => 3]);
    }

    public function finish(Request $request, PlatformInstaller $installer): RedirectResponse
    {
        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        $owner = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        $logo = null;
        if ($path = $request->session()->get('installation.logo')) {
            $absolute = storage_path('app/private/'.$path);
            if (is_file($absolute)) {
                $logo = new \Illuminate\Http\UploadedFile($absolute, basename($absolute), null, null, true);
            }
        }
        try {
            $installer->install(
                $request->session()->get('installation.platform'),
                $request->session()->get('installation.database'),
                $owner,
                $logo,
            );
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['installation' => 'Installation failed: '.$exception->getMessage()]);
        }
        $request->session()->invalidate();
        return redirect('/login')->with('status', 'Installation completed. Sign in with the super administrator account.');
    }

    private function freshInstallationSelected(Request $request): bool
    {
        return $request->session()->get('installation.mode') === 'fresh';
    }
}
