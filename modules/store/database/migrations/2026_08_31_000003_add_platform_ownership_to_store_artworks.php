<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('store_artwork_details', 'ownership_type')) {
            return;
        }

        Schema::table('store_artwork_details', function (Blueprint $table): void {
            $table->string('ownership_type', 24)
                ->default('artist_account')
                ->after('owner_user_id')
                ->index();
        });

        Schema::table('store_artwork_details', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_user_id')->nullable()->change();
            $table->index(['ownership_type', 'workflow_state'], 'store_artwork_ownership_state_idx');
        });

        DB::table('store_artwork_details')->update(['ownership_type' => 'artist_account']);
    }

    public function down(): void
    {
        if (DB::table('store_artwork_details')->where('ownership_type', 'platform')->exists()) {
            throw new \RuntimeException('Platform-owned artworks must be reassigned before rolling back this migration.');
        }

        Schema::table('store_artwork_details', function (Blueprint $table): void {
            $table->dropIndex('store_artwork_ownership_state_idx');
            $table->dropIndex(['ownership_type']);
            $table->dropColumn('ownership_type');
            $table->unsignedBigInteger('owner_user_id')->nullable(false)->change();
        });
    }
};
