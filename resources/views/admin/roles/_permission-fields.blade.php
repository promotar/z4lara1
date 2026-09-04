@php
    $permissionAccordionScope = $accordionScope ?? 'role-permissions';
@endphp

<section class="admin-permission-accordion" x-data="{ open: true }" data-role-permission-accordion="capabilities">
    <button type="button" class="admin-permission-accordion-button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="{{ $permissionAccordionScope }}-capabilities-panel">
        <span class="admin-permission-accordion-copy">
            <span class="admin-field-label">Capability Permissions</span>
            <span class="admin-field-hint">Capabilities remain an additional policy layer. Route access below is mandatory.</span>
        </span>
        <span class="admin-permission-accordion-meta">
            <span class="admin-access-count">{{ $permissions->count() }} capabilities</span>
            <span class="admin-permission-chevron" :class="{ 'is-open': open }" aria-hidden="true"></span>
        </span>
    </button>

    <div id="{{ $permissionAccordionScope }}-capabilities-panel" class="admin-permission-accordion-panel" x-show="open" x-transition.opacity.duration.150ms x-cloak>
        <div class="admin-option-grid">
            @foreach ($permissions as $permission)
                <label class="admin-option">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $selectedPermissions, true))>
                    <span>{{ $permission->name }}</span>
                </label>
            @endforeach
        </div>
    </div>
</section>

<section class="admin-permission-accordion" x-data="{ open: true }" data-role-permission-accordion="routes">
    <button type="button" class="admin-permission-accordion-button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="{{ $permissionAccordionScope }}-routes-panel">
        <span class="admin-permission-accordion-copy">
            <span class="admin-field-label">Mandatory Route Access</span>
            <span class="admin-field-hint">Active core and plugin routes are discovered automatically. An unchecked route is denied on the server and removed from the rendered interface.</span>
        </span>
        <span class="admin-permission-accordion-meta">
            <span class="admin-access-count">{{ $routePermissions->count() }} routes</span>
            <span class="admin-permission-chevron" :class="{ 'is-open': open }" aria-hidden="true"></span>
        </span>
    </button>

    <div id="{{ $permissionAccordionScope }}-routes-panel" class="admin-permission-accordion-panel admin-route-groups" x-show="open" x-transition.opacity.duration.150ms x-cloak>
        @foreach ($routePermissions->groupBy(function (array $entry): string {
            $parts = explode('.', $entry['name']);

            return ($parts[0] ?? '') === 'admin' && ($parts[1] ?? '') === 'plugins'
                ? 'Plugin: '.($parts[2] ?? 'unknown')
                : 'Core platform';
        }) as $group => $entries)
            <section class="admin-route-group-accordion" x-data="{ open: true }" data-route-permission-group="{{ $group }}">
                <button type="button" class="admin-route-group-accordion-button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="{{ $permissionAccordionScope }}-route-group-{{ $loop->index }}">
                    <span class="admin-access-card-title">{{ $group }}</span>
                    <span class="admin-permission-accordion-meta">
                        <span class="admin-access-count">{{ $entries->count() }} routes</span>
                        <span class="admin-permission-chevron" :class="{ 'is-open': open }" aria-hidden="true"></span>
                    </span>
                </button>

                <div id="{{ $permissionAccordionScope }}-route-group-{{ $loop->index }}" class="admin-route-group-accordion-panel" x-show="open" x-transition.opacity.duration.150ms x-cloak>
                    <div class="admin-option-grid">
                        @foreach ($entries as $entry)
                            <label class="admin-option">
                                <input type="checkbox" name="permissions[]" value="{{ $entry['permission'] }}" @checked(in_array($entry['permission'], $selectedPermissions, true))>
                                <span><strong>{{ $entry['name'] }}</strong><br><small>{{ $entry['methods'] }} /{{ $entry['uri'] }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</section>
