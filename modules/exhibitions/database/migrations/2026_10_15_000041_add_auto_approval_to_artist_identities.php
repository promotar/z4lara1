<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_artist_identities')) {
            return;
        }

        Schema::table('exhibition_artist_identities', function (Blueprint $table): void {
            if (! Schema::hasColumn('exhibition_artist_identities', 'auto_approve_artworks')) {
                $table->boolean('auto_approve_artworks')->default(false);
            }
            if (! Schema::hasColumn('exhibition_artist_identities', 'auto_approve_granted_by')) {
                $table->unsignedBigInteger('auto_approve_granted_by')->nullable();
            }
            if (! Schema::hasColumn('exhibition_artist_identities', 'auto_approve_granted_at')) {
                $table->timestamp('auto_approve_granted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exhibition_artist_identities')) {
            return;
        }

        Schema::table('exhibition_artist_identities', function (Blueprint $table): void {
            foreach (['auto_approve_artworks', 'auto_approve_granted_by', 'auto_approve_granted_at'] as $column) {
                if (Schema::hasColumn('exhibition_artist_identities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
