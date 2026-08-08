<?php

namespace App\Platform\Core\Access;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoutePermissionCatalog
{
    public const PREFIX = 'route.';

    public function __construct(private readonly Router $router) {}

    /**
     * @return Collection<int, array{name: string, permission: string, methods: string, uri: string, capabilities: list<string>}>
     */
    public function routes(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => $this->isProtected($route) && filled($route->getName()))
            ->map(fn (Route $route): array => [
                'name' => (string) $route->getName(),
                'permission' => $this->permissionName($route),
                'methods' => implode('|', array_values(array_diff($route->methods(), ['HEAD']))),
                'uri' => $route->uri(),
                'capabilities' => $this->capabilities($route),
            ])
            ->sortBy(fn (array $entry): array => [$entry['uri'], $entry['methods'], $entry['name']])
            ->values();
    }

    public function sync(bool $grantExistingAccess = false): int
    {
        $entries = $this->routes();
        $names = $entries->pluck('permission');
        $existing = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('name');
        $now = now();
        $created = Permission::query()->insertOrIgnore(
            $names->diff($existing)
                ->map(fn (string $name): array => [
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all(),
        );

        if ($created > 0) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if ($grantExistingAccess) {
            $this->grantExistingRoleAccess($entries);
        }

        return $created;
    }

    public function permissionName(Route|string $route): string
    {
        $name = $route instanceof Route ? $route->getName() : $route;

        return self::PREFIX.(string) $name;
    }

    public function isProtected(Route $route): bool
    {
        $middleware = $route->gatherMiddleware();

        return str_starts_with(trim($route->uri(), '/'), 'admin/')
            || $route->getName() === 'dashboard'
            || in_array('staff', $middleware, true)
            || in_array('super-admin', $middleware, true)
            || collect($middleware)->contains(fn (string $name): bool => str_starts_with($name, 'permission:'));
    }

    /** @return list<string> */
    public function capabilities(Route $route): array
    {
        return collect($route->gatherMiddleware())
            ->filter(fn (string $name): bool => str_starts_with($name, 'permission:'))
            ->flatMap(fn (string $name): array => preg_split('/[|,]/', substr($name, strlen('permission:'))) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Preserve only access that a role already had before exact route permissions existed.
     *
     * @param  Collection<int, array{name: string, permission: string, methods: string, uri: string, capabilities: list<string>}>  $entries
     */
    private function grantExistingRoleAccess(Collection $entries): void
    {
        foreach (Role::with('permissions')->where('guard_name', 'web')->get() as $role) {
            $existing = $role->permissions->pluck('name')->all();
            $routePermissions = [];

            foreach ($entries as $entry) {
                $route = $this->router->getRoutes()->getByName($entry['name']);

                if ($route !== null && $this->rolePreviouslyAllowed($role, $route, $existing)) {
                    $routePermissions[] = $entry['permission'];
                }
            }

            if ($routePermissions !== []) {
                $role->givePermissionTo($routePermissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param list<string> $permissions */
    private function rolePreviouslyAllowed(Role $role, Route $route, array $permissions): bool
    {
        $middleware = $route->gatherMiddleware();

        if ($role->name === 'super-admin') {
            return true;
        }

        if (in_array('super-admin', $middleware, true)) {
            return false;
        }

        $requiresStaff = str_starts_with(trim($route->uri(), '/'), 'admin/')
            || $route->getName() === 'dashboard'
            || in_array('staff', $middleware, true);

        if ($requiresStaff && ! in_array($role->name, ['admin', 'staff', 'employee'], true)) {
            return false;
        }

        return collect($this->capabilities($route))->every(fn (string $permission): bool => in_array($permission, $permissions, true));
    }
}
