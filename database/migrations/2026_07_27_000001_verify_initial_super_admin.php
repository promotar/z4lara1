<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('model_has_roles')
        ) {
            return;
        }

        $owner = User::query()
            ->role('super-admin')
            ->orderBy('id')
            ->first();

        if ($owner !== null && $owner->email_verified_at === null) {
            $owner->forceFill(['email_verified_at' => now()])->save();
        }
    }

    public function down(): void
    {
        // Email verification is an identity fact and must not be rolled back.
    }
};
