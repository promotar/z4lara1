<?php

namespace Database\Seeders;

use App\Platform\Core\Services\PermissionManager;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(PermissionManager $permissions): void
    {
        $permissions->syncDefaults();
    }
}
