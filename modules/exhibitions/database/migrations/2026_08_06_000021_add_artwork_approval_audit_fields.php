<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsApprovedBy = ! Schema::hasColumn('exhibition_artworks', 'approved_by');
        $needsRound = ! Schema::hasColumn('exhibition_artworks', 'approval_review_round_id');
        Schema::table('exhibition_artworks', function (Blueprint $table) use ($needsApprovedBy, $needsRound): void {
            if ($needsApprovedBy) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at')->index('ex_artwork_approved_by_idx');
            }
            if ($needsRound) {
                $table->unsignedBigInteger('approval_review_round_id')->nullable()->after('approved_by')->index('ex_artwork_approval_round_idx');
            }
        });

        $needsHistoryRound = ! Schema::hasColumn('exhibition_artwork_state_history', 'review_round_id');
        $needsEventKey = ! Schema::hasColumn('exhibition_artwork_state_history', 'event_key');
        Schema::table('exhibition_artwork_state_history', function (Blueprint $table) use ($needsHistoryRound, $needsEventKey): void {
            if ($needsHistoryRound) {
                $table->unsignedBigInteger('review_round_id')->nullable()->after('reason');
            }
            if ($needsEventKey) {
                $table->string('event_key', 64)->nullable()->unique('ex_artwork_state_event_key_uq')->after('review_round_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exhibition_artwork_state_history', function (Blueprint $table): void {
            if (Schema::hasColumn('exhibition_artwork_state_history', 'event_key')
                && Schema::hasIndex('exhibition_artwork_state_history', 'ex_artwork_state_event_key_uq')) {
                $table->dropUnique('ex_artwork_state_event_key_uq');
                $table->dropColumn('event_key');
            }
            if (Schema::hasColumn('exhibition_artwork_state_history', 'review_round_id')) {
                $table->dropColumn('review_round_id');
            }
        });
        Schema::table('exhibition_artworks', function (Blueprint $table): void {
            if (Schema::hasColumn('exhibition_artworks', 'approval_review_round_id')) {
                $table->dropIndex('ex_artwork_approval_round_idx');
                $table->dropColumn('approval_review_round_id');
            }
            if (Schema::hasColumn('exhibition_artworks', 'approved_by')) {
                $table->dropIndex('ex_artwork_approved_by_idx');
                $table->dropColumn('approved_by');
            }
        });
    }
};
