<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform installation</title>
    @vite(['resources/css/app.css'])
</head>
<body class="installation-shell">
<main class="installer-layout">
    <div class="installer-brand">Art INPA Installation</div>

    @if($step > 0)
        <div class="installer-steps" aria-label="Installation progress">
            @if($mode === 'fresh')
                <div class="installer-step {{ $step === 1 ? 'is-active' : '' }}">1. Platform</div>
                <div class="installer-step {{ $step === 2 ? 'is-active' : '' }}">2. Database</div>
                <div class="installer-step {{ $step === 3 ? 'is-active' : '' }}">3. Owner</div>
            @else
                <div class="installer-step">1. Update mode</div>
                <div class="installer-step is-active">2. Existing database</div>
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
            <h1>Choose installation mode</h1>
            <p>Select how this deployment should prepare the platform database.</p>
            <div class="installer-choice-grid">
                <article class="installer-choice installer-choice--fresh">
                    <div class="installer-choice__icon" aria-hidden="true">+</div>
                    <h2>New installation</h2>
                    <p>Erases every existing table in the selected database, runs all migrations and seeders, and creates a new owner account.</p>
                    <form method="post" action="{{ route('install.mode') }}">
                        @csrf
                        <input type="hidden" name="mode" value="fresh">
                        <button class="installer-button" type="submit">Start new installation</button>
                    </form>
                </article>
                <article class="installer-choice">
                    <div class="installer-choice__icon" aria-hidden="true">&#8635;</div>
                    <h2>Update existing installation</h2>
                    <p>Connects to the existing platform database and runs pending migrations only. Existing tables, users, settings, content, and plugin data are preserved.</p>
                    <form method="post" action="{{ route('install.mode') }}">
                        @csrf
                        <input type="hidden" name="mode" value="update">
                        <button class="installer-button installer-button--update" type="submit">Update without deleting data</button>
                    </form>
                </article>
            </div>
        @elseif($step === 1)
            <h1>Platform identity</h1>
            <p>Set the public identity for this installation.</p>
            <form method="post" action="{{ route('install.platform.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="installer-label" for="name">Platform name</label>
                <input class="installer-input" id="name" name="name" value="{{ old('name') }}" required>
                <label class="installer-label" for="domain">Domain</label>
                <input class="installer-input" id="domain" name="domain" type="url" placeholder="https://example.com" value="{{ old('domain', $domainDefault ?? '') }}" required>
                <label class="installer-label" for="logo">Logo</label>
                <input class="installer-input" id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp">
                <button class="installer-button" type="submit">Continue</button>
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
                        <label class="installer-label" for="host">Database host / IP</label>
                        <input class="installer-input" id="host" name="host" value="{{ old('host', $databaseDefaults['host'] ?? '') }}" required>
                    </div>
                    <div>
                        <label class="installer-label" for="port">Port</label>
                        <input class="installer-input" id="port" name="port" type="number" value="{{ old('port', $databaseDefaults['port'] ?? 3306) }}" required>
                    </div>
                </div>
                <label class="installer-label" for="database">Database name</label>
                <input class="installer-input" id="database" name="database" value="{{ old('database', $databaseDefaults['database'] ?? '') }}" required>
                <label class="installer-label" for="username">Database username</label>
                <input class="installer-input" id="username" name="username" value="{{ old('username', $databaseDefaults['username'] ?? '') }}" required>
                <label class="installer-label" for="password">Database password</label>
                <input class="installer-input" id="password" name="password" type="password" autocomplete="new-password">
                @if($mode === 'fresh')
                    <div class="installer-warning"><strong>Destructive operation:</strong> continuing will permanently delete every existing table in this database and install a fresh platform database.</div>
                    <label class="installer-check"><input name="erase_confirmation" type="checkbox" value="1" required><span>I understand and authorize deleting all existing database tables.</span></label>
                    <button class="installer-button" type="submit">Test connection and continue</button>
                @else
                    <div class="installer-update-note"><strong>Safe update:</strong> this process runs pending migrations and never calls <code>migrate:fresh</code>, truncates tables, or replaces existing records.</div>
                    <button class="installer-button installer-button--update" type="submit">Test connection and update</button>
                @endif
            </form>
        @else
            <h1>Super administrator</h1>
            <p>Create the verified owner account with all platform permissions.</p>
            <form method="post" action="{{ route('install.finish') }}">
                @csrf
                <label class="installer-label" for="email">Email</label>
                <input class="installer-input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                <label class="installer-label" for="password">Password</label>
                <input class="installer-input" id="password" name="password" type="password" minlength="10" required>
                <label class="installer-label" for="password_confirmation">Confirm password</label>
                <input class="installer-input" id="password_confirmation" name="password_confirmation" type="password" minlength="10" required>
                <button class="installer-button" type="submit">Erase database and install</button>
            </form>
        @endif
    </section>
</main>
</body>
</html>
