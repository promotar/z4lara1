<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_plugin_registry_entries')) {
            Schema::create('platform_plugin_registry_entries', function (Blueprint $table): void {
                $table->id();
                $table->string('registry_type');
                $table->string('plugin_slug');
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique(['registry_type', 'plugin_slug'], 'ppre_type_slug_unique');
                $table->index('registry_type', 'ppre_type_index');
            });

            return;
        }

        try {
            DB::statement('ALTER TABLE platform_plugin_registry_entries ADD UNIQUE KEY ppre_type_slug_unique (registry_type, plugin_slug)');
        } catch (Throwable) {
            //
        }

        try {
            DB::statement('ALTER TABLE platform_plugin_registry_entries ADD INDEX ppre_type_index (registry_type)');
        } catch (Throwable) {
            //
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_plugin_registry_entries');
    }
};
