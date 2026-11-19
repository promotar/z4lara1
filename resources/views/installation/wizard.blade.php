<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-i18n="core.install.subtitle">{{ __('core.install.subtitle') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="installation-shell">
<main class="installer-layout">
    <div class="installer-brand" data-i18n="core.install.title">{{ __('core.install.title') }}</div>

    @if($step > 0)
        <div class="installer-steps" aria-label="{{ __('core.install.progress') }}">
            @if($mode === 'fresh')
                <div class="installer-step {{ $step === 1 ? 'is-active' : '' }}" data-i18n="core.install.step_platform">{{ __('core.install.step_platform') }}</div>
                <div class="installer-step {{ $step === 2 ? 'is-active' : '' }}" data-i18n="core.install.step_database">{{ __('core.install.step_database') }}</div>
                <div class="installer-step {{ $step === 3 ? 'is-active' : '' }}" data-i18n="core.install.step_owner">{{ __('core.install.step_owner') }}</div>
            @else
                <div class="installer-step" data-i18n="core.install.step_update">{{ __('core.install.step_update') }}</div>
                <div class="installer-step is-active" data-i18n="core.install.step_existing">{{ __('core.install.step_existing') }}</div>
            @endif
        </div>
    @endif

    <section class="installer-panel">
        @if($errors->any())
            <div class="installer-errors" role="alert">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if($step === 0)
            <h1 data-i18n="core.install.mode_title">{{ __('core.install.mode_title') }}</h1>
            <p data-i18n="core.install.mode_hint">{{ __('core.install.mode_hint') }}</p>
            <div class="installer-choice-grid">
                <article class="installer-choice installer-choice--fresh">
                    <div class="installer-choice__icon" aria-hidden="true">+</div>
                    <h2 data-i18n="core.install.new_install">{{ __('core.install.new_install') }}</h2>
                    <p data-i18n="core.install.new_install_hint">{{ __('core.install.new_install_hint') }}</p>
                    <form method="post" action="{{ route('install.mode') }}">
                        @csrf
                        <input type="hidden" name="mode" value="fresh">
                        <button class="installer-button" type="submit" data-i18n="core.install.start_new">{{ __('core.install.start_new') }}</button>
                    </form>
                </article>
                <article class="installer-choice">
                    <div class="installer-choice__icon" aria-hidden="true">&#8635;</div>
                    <h2 data-i18n="core.install.update_title">{{ __('core.install.update_title') }}</h2>
                    <p>Connects to the existing platform database and runs pending migrations only. Existing tables, users, settings, content, and plugin data are preserved.</p>
                    <form method="post" action="{{ route('install.mode') }}">
                        @csrf
                        <input type="hidden" name="mode" value="update">
                        <button class="installer-button installer-button--update" type="submit" data-i18n="core.install.update_button">{{ __('core.install.update_button') }}</button>
                    </form>
                </article>
            </div>
        @elseif($step === 1)
            <h1 data-i18n="core.install.identity_title">{{ __('core.install.identity_title') }}</h1>
            <p data-i18n="core.install.identity_hint">{{ __('core.install.identity_hint') }}</p>
            <form method="post" action="{{ route('install.platform.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="installer-label" for="name" data-i18n="core.install.platform_name">{{ __('core.install.platform_name') }}</label>
                <input class="installer-input" id="name" name="name" value="{{ old('name') }}" required>
                <label class="installer-label" for="domain" data-i18n="core.install.domain">{{ __('core.install.domain') }}</label>
                <input class="installer-input" id="domain" name="domain" type="url" placeholder="{{ __('core.install.domain_placeholder') }}" value="{{ old('domain', $domainDefault ?? '') }}" required>
                <label class="installer-label" for="logo" data-i18n="core.install.logo">{{ __('core.install.logo') }}</label>
                <input class="installer-input" id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp">
                <button class="installer-button" type="submit" data-i18n="core.install.continue">{{ __('core.install.continue') }}</button>
            </form>
        @elseif($step === 2)
            <h1>{{ $mode === 'fresh' ? 'New database connection' : 'Existing database connection' }}</h1>
            <p>
                @if($mode === 'fresh')
                    The connection is tested before the database is erased and rebuilt.
                @else
                    The connection is tested before pending migrations run. Existing data is preserved.
                @endif
            </p>
            <form method="post" action="{{ route('install.database.store') }}">
                @csrf
                <div class="installer-grid">
                    <div>
                        <label class="installer-label" for="host" data-i18n="core.install.db_host">{{ __('core.install.db_host') }}</label>
                        <input class="installer-input" id="host" name="host" value="{{ old('host', $databaseDefaults['host'] ?? '') }}" required>
                    </div>
                    <div>
                        <label class="installer-label" for="port" data-i18n="core.install.port">{{ __('core.install.port') }}</label>
                        <input class="installer-input" id="port" name="port" type="number" value="{{ old('port', $databaseDefaults['port'] ?? 3306) }}" required>
                    </div>
                </div>
                <label class="installer-label" for="database" data-i18n="core.install.db_name">{{ __('core.install.db_name') }}</label>
                <input class="installer-input" id="database" name="database" value="{{ old('database', $databaseDefaults['database'] ?? '') }}" required>
                <label class="installer-label" for="username" data-i18n="core.install.db_user">{{ __('core.install.db_user') }}</label>
                <input class="installer-input" id="username" name="username" value="{{ old('username', $databaseDefaults['username'] ?? '') }}" required>
                <label class="installer-label" for="password" data-i18n="core.install.db_password">{{ __('core.install.db_password') }}</label>
                <input class="installer-input" id="password" name="password" type="password" autocomplete="new-password">
                @if($mode === 'fresh')
                    <div class="installer-warning"><strong data-i18n="core.install.destructive">{{ __('core.install.destructive') }}</strong data-i18n="core.install.destructive_hint"> {{ __('core.install.destructive_hint') }}</div>
                    <label class="installer-check"><input name="erase_confirmation" type="checkbox" value="1" required><span data-i18n="core.install.confirm_delete">{{ __('core.install.confirm_delete') }}</span></label>
                    <button class="installer-button" type="submit" data-i18n="core.install.test_continue">{{ __('core.install.test_continue') }}</button>
                @else
                    <div class="installer-update-note"><strong data-i18n="core.install.safe_update">{{ __('core.install.safe_update') }}</strong data-i18n="core.install.safe_hint"> {{ __('core.install.safe_hint') }} <code>migrate:fresh</code data-i18n="core.install.safe_hint2">{{ __('core.install.safe_hint2') }}</div>
                    <button class="installer-button installer-button--update" type="submit" data-i18n="core.install.test_update">{{ __('core.install.test_update') }}</button>
                @endif
            </form>
        @else
            <h1 data-i18n="core.install.owner_title">{{ __('core.install.owner_title') }}</h1>
            <p data-i18n="core.install.owner_hint">{{ __('core.install.owner_hint') }}</p>
            <form method="post" action="{{ route('install.finish') }}">
                @csrf
                <label class="installer-label" for="email" data-i18n="core.install.email">{{ __('core.install.email') }}</label>
                <input class="installer-input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                <label class="installer-label" for="password" data-i18n="core.install.password">{{ __('core.install.password') }}</label>
                <input class="installer-input" id="password" name="password" type="password" minlength="10" required>
                <label class="installer-label" for="password_confirmation" data-i18n="core.install.confirm_password">{{ __('core.install.confirm_password') }}</label>
                <input class="installer-input" id="password_confirmation" name="password_confirmation" type="password" minlength="10" required>
                <button class="installer-button" type="submit" data-i18n="core.install.erase_install">{{ __('core.install.erase_install') }}</button>
            </form>
        @endif
    </section>
</main>
</body>
</html>
