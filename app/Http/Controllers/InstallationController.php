<?php

namespace App\Http\Controllers;

use App\Installation\InstallationState;
use App\Installation\PlatformInstaller;
use App\Installation\ProxyConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Throwable;

final class InstallationController extends Controller
{
    public function __construct(
        private readonly InstallationState $state,
        private readonly ProxyConfiguration $proxy,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->state->installed()) {
            return redirect('/');
        }

        $request->session()->forget('installation');

        return view('installation.wizard', [
            'step' => 0,
            'mode' => null,
            'domainDefault' => $this->proxy->publicUrl($request),
        ]);
    }

    public function chooseMode(Request $request): RedirectResponse
    {
        if ($this->state->installed()) {
            return redirect('/');
        }

        $validated = $request->validate([
            'mode' => ['required', 'in:fresh,update'],
        ]);

        $request->session()->forget('installation');
        $request->session()->put('installation.mode', $validated['mode']);
        $request->session()->put('installation.runtime', [
            'app_url' => $this->proxy->publicUrl($request),
            'trusted_proxies' => $this->proxy->trustedProxies($request),
        ]);

        return redirect()->route($validated['mode'] === 'fresh' ? 'install.platform' : 'install.database');
    }

    public function platform(Request $request): View|RedirectResponse
    {
        if ($this->state->installed()) {
            return redirect('/');
        }

        if (! $this->freshInstallationSelected($request)) {
            return redirect()->route('install.index');
        }

        return view('installation.wizard', [
            'step' => 1,
            'mode' => 'fresh',
            'domainDefault' => (string) $request->session()->get(
                'installation.runtime.app_url',
                $this->proxy->publicUrl($request),
            ),
        ]);
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
        $request->session()->put('installation.platform', [
            'name' => $validated['name'],
            'domain' => rtrim($validated['domain'], '/'),
            'trusted_proxies' => (string) $request->session()->get(
                'installation.runtime.trusted_proxies',
                $this->proxy->trustedProxies($request),
            ),
        ]);
        if ($request->hasFile('logo')) {
            $request->session()->put('installation.logo', $request->file('logo')->store('installation', 'local'));
        }

        return redirect()->route('install.database');
    }

    public function database(Request $request): View|RedirectResponse
    {
        if (! $this->installationModeSelected($request)) {
            return redirect()->route('install.index');
        }

        $mode = (string) $request->session()->get('installation.mode');
        if ($mode === 'fresh' && ! $request->session()->has('installation.platform')) {
            return redirect()->route('install.platform');
        }

        $connection = (array) config('database.connections.mysql', []);

        return view('installation.wizard', [
            'step' => 2,
            'mode' => $mode,
            'databaseDefaults' => [
                'host' => (string) ($connection['host'] ?? ''),
                'port' => (string) ($connection['port'] ?? '3306'),
                'database' => (string) ($connection['database'] ?? ''),
                'username' => (string) ($connection['username'] ?? ''),
            ],
        ]);
    }

    public function storeDatabase(Request $request, PlatformInstaller $installer): RedirectResponse
    {
        if (! $this->installationModeSelected($request)) {
            return redirect()->route('install.index');
        }

        $mode = (string) $request->session()->get('installation.mode');
        $rules = [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:500'],
        ];
        if ($mode === 'fresh') {
            $rules['erase_confirmation'] = ['accepted'];
        }

        $database = $request->validate($rules);
        unset($database['erase_confirmation']);

        if ($mode === 'update' && ($database['password'] ?? '') === '') {
            $database['password'] = (string) config('database.connections.mysql.password', '');
        }

        try {
            if ($mode === 'update') {
                $installer->update(
                    $database,
                    (array) $request->session()->get('installation.runtime', []),
                );
            } else {
                $installer->testDatabase($database);
            }
        } catch (Throwable $exception) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $exception->getMessage()]);
        }

        if ($mode === 'update') {
            $request->session()->invalidate();

            return redirect('/login')->with('status', 'Platform update completed. Existing data was preserved.');
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

        return view('installation.wizard', ['step' => 3, 'mode' => 'fresh']);
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
                $logo = new UploadedFile($absolute, basename($absolute), null, null, true);
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

    private function installationModeSelected(Request $request): bool
    {
        return in_array($request->session()->get('installation.mode'), ['fresh', 'update'], true);
    }
}
