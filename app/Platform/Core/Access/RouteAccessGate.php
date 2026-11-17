<?php

namespace App\Platform\Core\Access;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

final class RouteAccessGate
{
    /** @var array<int, array{roles: list<string>, permissions: list<string>}> */
    private array $snapshots = [];

    public function __construct(
        private readonly Router $router,
        private readonly RoutePermissionCatalog $catalog,
    ) {}

    public function allows(?User $user, Route $route): bool
    {
        if (! $this->catalog->isProtected($route)) {
            return true;
        }

        if ($user === null || blank($route->getName())) {
            return false;
        }

        $snapshot = $this->snapshot($user);

        return in_array('super-admin', $snapshot['roles'], true)
            || in_array($this->catalog->permissionName($route), $snapshot['permissions'], true);
    }

    public function allowsRouteName(?User $user, ?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return true;
        }

        $route = $this->router->getRoutes()->getByName($routeName);

        return $route === null || $this->allows($user, $route);
    }

    public function isSuperAdmin(?User $user): bool
    {
        return $user !== null && in_array('super-admin', $this->snapshot($user)['roles'], true);
    }

    public function beginRequest(?User $user): void
    {
        if ($user !== null) {
            unset($this->snapshots[$user->getKey()]);
        }
    }

    /** @return array{roles: list<string>, permissions: list<string>} */
    private function snapshot(User $user): array
    {
        return $this->snapshots[$user->getKey()] ??= [
            'roles' => $user->roles()->where('guard_name', 'web')->pluck('name')->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
