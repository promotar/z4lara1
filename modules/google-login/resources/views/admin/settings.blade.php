<x-app-layout>
    <div class="google-login-settings">
        <header class="google-login-settings__header">
            <div>
                <span>Authentication</span>
                <h1>Google Login</h1>
                <p>Make Google the primary login option while keeping the classic email and password form.</p>
            </div>
            <span class="google-login-status {{ $settings['configured'] ? 'is-ready' : '' }}">
                {{ $settings['configured'] ? 'Credentials ready' : 'Setup required' }}
            </span>
        </header>

        @if (session('status'))
            <div class="google-login-notice is-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="google-login-notice is-error">{{ $errors->first() }}</div>
        @endif

        <div class="google-login-settings__grid">
            <form method="POST" action="{{ route('admin.plugins.google-login.settings.update') }}" class="google-login-settings__card">
                @csrf
                @method('PUT')

                <section>
                    <h2>Google OAuth credentials</h2>
                    <p>Create a Web application in Google Cloud and copy its credentials here.</p>

                    <label>
                        <span>Client ID</span>
                        <input type="text" name="client_id" value="{{ old('client_id', $settings['client_id']) }}" autocomplete="off" placeholder="000000000000-xxxx.apps.googleusercontent.com">
                    </label>

                    <label>
                        <span>Client Secret</span>
                        <input type="password" name="client_secret" value="" autocomplete="new-password" placeholder="{{ $settings['has_client_secret'] ? 'Secret is securely stored — leave blank to keep it' : 'Enter the Google client secret' }}">
                    </label>

                    @if ($settings['has_client_secret'])
                        <label class="google-login-check is-danger">
                            <input type="checkbox" name="clear_client_secret" value="1">
                            <span>Remove the stored client secret</span>
                        </label>
                    @endif
                </section>

                <section>
                    <h2>Login policy</h2>

                    <label>
                        <span>Button text</span>
                        <input type="text" name="button_text" value="{{ old('button_text', $settings['button_text']) }}" maxlength="80" required>
                    </label>

                    <label>
                        <span>Allowed Google Workspace domain <small>optional</small></span>
                        <input type="text" name="hosted_domain" value="{{ old('hosted_domain', $settings['hosted_domain']) }}" placeholder="example.com">
                        <small>Leave empty to allow verified Google accounts from any domain.</small>
                    </label>

                    <label class="google-login-check">
                        <input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration', $settings['allow_registration']))>
                        <span>Automatically create a local account for a new verified Google email</span>
                    </label>

                    <label class="google-login-check is-primary">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                        <span>Enable Google Login on the platform login page</span>
                    </label>
                </section>

                <footer>
                    <button type="submit">Save Google Login settings</button>
                </footer>
            </form>

            <aside class="google-login-settings__card google-login-setup">
                <h2>Google Cloud setup</h2>
                <ol>
                    <li>Open Google Cloud Console and configure the OAuth consent screen.</li>
                    <li>Create an <strong>OAuth client ID</strong> with application type <strong>Web application</strong>.</li>
                    <li>Add the exact callback URL below to <strong>Authorized redirect URIs</strong>.</li>
                    <li>Save the Client ID and Client Secret, then enable Google Login here.</li>
                </ol>

                <label>
                    <span>Authorized redirect URI</span>
                    <div class="google-login-copy-row">
                        <input type="text" value="{{ $callbackUrl }}" readonly data-google-callback-url>
                        <button type="button" data-copy-google-callback>Copy</button>
                    </div>
                </label>

                <div class="google-login-security-note">
                    <strong>Security included</strong>
                    <p>State and nonce validation, Google RSA signature verification, verified-email enforcement, encrypted secret storage, no Google token persistence, and route rate limiting.</p>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.querySelector('[data-copy-google-callback]')?.addEventListener('click', async (event) => {
            const input = document.querySelector('[data-google-callback-url]');
            if (!input) return;
            try {
                await navigator.clipboard.writeText(input.value);
                event.currentTarget.textContent = 'Copied';
                setTimeout(() => event.currentTarget.textContent = 'Copy', 1400);
            } catch {
                input.select();
            }
        });
    </script>
</x-app-layout>
