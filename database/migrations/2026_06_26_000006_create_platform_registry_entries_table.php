<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_registry_entries')) {
            Schema::create('platform_registry_entries', function (Blueprint $table): void {
                $table->id();
                $table->string('registry_type', 40);
                $table->string('registry_key');
                $table->json('payload')->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->timestamps();

                $table->unique(['registry_type', 'registry_key'], 'pre_type_key_unique');
                $table->index('registry_type', 'pre_type_index');
            });
        }

        DB::table('platform_registry_entries')->updateOrInsert(
            [
                'registry_type' => 'routes',
                'registry_key' => 'pages.show',
            ],
            [
                'payload' => json_encode([
                    'uri' => 'pages/{slug}',
                    'methods' => ['GET', 'HEAD'],
                    'description' => 'Render published platform page builder content',
                    'module' => 'core',
                    'status' => 'active',
                ], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_registry_entries')) {
            return;
        }

        DB::table('platform_registry_entries')
            ->where('registry_type', 'routes')
            ->where('registry_key', 'pages.show')
            ->delete();
    }
};
