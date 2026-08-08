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

    <div class="installer-steps" aria-label="Installation progress">
        <div class="installer-step {{ $step === 1 ? 'is-active' : '' }}">1. Platform</div>
        <div class="installer-step {{ $step === 2 ? 'is-active' : '' }}">2. Database</div>
        <div class="installer-step {{ $step === 3 ? 'is-active' : '' }}">3. Owner</div>
    </div>

    <section class="installer-panel">
        @if($errors->any())
            <div class="installer-errors" role="alert">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if($step === 1)
            <h1>Platform identity</h1>
            <p>Set the public identity for this installation.</p>
            <form method="post" action="{{ route('install.platform.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="installer-label" for="name">Platform name</label>
                <input class="installer-input" id="name" name="name" value="{{ old('name') }}" required>
                <label class="installer-label" for="domain">Domain</label>
                <input class="installer-input" id="domain" name="domain" type="url" placeholder="https://example.com" value="{{ old('domain') }}" required>
                <label class="installer-label" for="logo">Logo</label>
                <input class="installer-input" id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp">
                <button class="installer-button" type="submit">Continue</button>
            </form>
        @elseif($step === 2)
            <h1>Database connection</h1>
            <p>The connection is tested before any database operation.</p>
            <form method="post" action="{{ route('install.database.store') }}">
                @csrf
                <div class="installer-grid">
                    <div>
                        <label class="installer-label" for="host">Database host / IP</label>
                        <input class="installer-input" id="host" name="host" value="{{ old('host') }}" required>
                    </div>
                    <div>
                        <label class="installer-label" for="port">Port</label>
                        <input class="installer-input" id="port" name="port" type="number" value="{{ old('port', 3306) }}" required>
                    </div>
                </div>
                <label class="installer-label" for="database">Database name</label>
                <input class="installer-input" id="database" name="database" value="{{ old('database') }}" required>
                <label class="installer-label" for="username">Database username</label>
                <input class="installer-input" id="username" name="username" value="{{ old('username') }}" required>
                <label class="installer-label" for="password">Database password</label>
                <input class="installer-input" id="password" name="password" type="password">
                <div class="installer-warning"><strong>Destructive operation:</strong> continuing will permanently delete every existing table in this database and install a fresh platform database.</div>
                <label class="installer-check"><input name="erase_confirmation" type="checkbox" value="1" required><span>I understand and authorize deleting all existing database tables.</span></label>
                <button class="installer-button" type="submit">Test connection and continue</button>
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
