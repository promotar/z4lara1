<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exhibition_agreement_templates', 'is_active')) {
            Schema::table('exhibition_agreement_templates', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->index('exhibition_agreement_templates_active_idx');
            });
        }
        if (! Schema::hasColumn('exhibition_agreement_templates', 'is_default')) {
            Schema::table('exhibition_agreement_templates', function (Blueprint $table): void {
                $table->boolean('is_default')->default(false)->index('exhibition_agreement_templates_default_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exhibition_agreement_templates', 'is_default')) {
            Schema::table('exhibition_agreement_templates', function (Blueprint $table): void {
                $table->dropIndex('exhibition_agreement_templates_default_idx');
                $table->dropColumn('is_default');
            });
        }
        if (Schema::hasColumn('exhibition_agreement_templates', 'is_active')) {
            Schema::table('exhibition_agreement_templates', function (Blueprint $table): void {
                $table->dropIndex('exhibition_agreement_templates_active_idx');
                $table->dropColumn('is_active');
            });
        }
    }
};
