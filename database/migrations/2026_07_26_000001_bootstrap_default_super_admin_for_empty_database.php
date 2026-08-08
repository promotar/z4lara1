<?php

use App\Models\User;
use App\Platform\Core\Services\PermissionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            return;
        }

        if (User::query()->exists()) {
            return;
        }

        $account = config('platform_bootstrap.default_super_admin');

        $user = User::query()->forceCreate([
            'name' => $account['name'],
            'email' => $account['email'],
            'email_verified_at' => now(),
            'password' => $account['password'],
        ]);

        app(PermissionManager::class)->assignSuperAdmin($user);
    }

    public function down(): void
    {
        //
    }
};
