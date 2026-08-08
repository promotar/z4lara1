<?php

namespace App\Console\Commands;

use App\Platform\Core\Access\RoutePermissionCatalog;
use Illuminate\Console\Command;

final class SyncRoutePermissions extends Command
{
    protected $signature = 'platform:sync-route-permissions {--grant-existing : Preserve access currently granted by staff roles and capability middleware}';

    protected $description = 'Synchronize mandatory per-route permissions from the active Laravel route collection';

    public function handle(RoutePermissionCatalog $catalog): int
    {
        $created = $catalog->sync((bool) $this->option('grant-existing'));

        $this->info("Route permissions synchronized. Created: {$created}; protected routes: {$catalog->routes()->count()}.");

        return self::SUCCESS;
    }
}
