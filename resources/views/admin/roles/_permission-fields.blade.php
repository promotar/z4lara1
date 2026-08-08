<section>
    <div class="admin-field-label">Capability Permissions</div>
    <p class="admin-field-hint">Capabilities remain an additional policy layer. Route access below is mandatory.</p>
    <div class="admin-option-grid">
        @foreach ($permissions as $permission)
            <label class="admin-option">
                <input
                    type="checkbox"
                    name="permissions[]"
                    value="{{ $permission->name }}"
                    @checked(in_array($permission->name, $selectedPermissions, true))
                >
                <span>{{ $permission->name }}</span>
            </label>
        @endforeach
    </div>
</section>

<section>
    <div class="admin-field-label">Mandatory Route Access</div>
    <p class="admin-field-hint">Active core and plugin routes are discovered automatically. An unchecked route is denied on the server and removed from the rendered interface.</p>

    @foreach ($routePermissions->groupBy(function (array $entry): string {
        $parts = explode('.', $entry['name']);

        return ($parts[0] ?? '') === 'admin' && ($parts[1] ?? '') === 'plugins'
            ? 'Plugin: '.($parts[2] ?? 'unknown')
            : 'Core platform';
    }) as $group => $entries)
        <div class="admin-access-card-header">
            <h4 class="admin-access-card-title">{{ $group }}</h4>
            <span class="admin-access-count">{{ $entries->count() }} routes</span>
        </div>

        <div class="admin-option-grid">
            @foreach ($entries as $entry)
                <label class="admin-option">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="{{ $entry['permission'] }}"
                        @checked(in_array($entry['permission'], $selectedPermissions, true))
                    >
                    <span>
                        <strong>{{ $entry['name'] }}</strong><br>
                        <small>{{ $entry['methods'] }} /{{ $entry['uri'] }}</small>
                    </span>
                </label>
            @endforeach
        </div>
    @endforeach
</section>
