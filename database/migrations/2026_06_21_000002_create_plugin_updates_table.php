<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plugin_updates')) {
            Schema::table('plugin_updates', function (Blueprint $table): void {
                if (! Schema::hasColumn('plugin_updates', 'plugin_id')) {
                    $table->foreignId('plugin_id')->nullable()->constrained('plugins')->cascadeOnDelete();
                }

                if (! Schema::hasColumn('plugin_updates', 'current_version')) {
                    $table->string('current_version', 50)->nullable();
                }

                if (! Schema::hasColumn('plugin_updates', 'available_version')) {
                    $table->string('available_version', 50)->nullable();
                }

                if (! Schema::hasColumn('plugin_updates', 'changelog')) {
                    $table->json('changelog')->nullable();
                }

                if (! Schema::hasColumn('plugin_updates', 'package_url')) {
                    $table->string('package_url')->nullable();
                }

                if (! Schema::hasColumn('plugin_updates', 'checked_at')) {
                    $table->timestamp('checked_at')->nullable();
                }

                if (! Schema::hasColumn('plugin_updates', 'installed_at')) {
                    $table->timestamp('installed_at')->nullable();
                }
            });

            if (Schema::hasColumn('plugin_updates', 'plugin_slug') && Schema::hasColumn('plugin_updates', 'plugin_id')) {
                if (DB::getDriverName() === 'sqlite') {
                    DB::statement(<<<'SQL'
                        UPDATE plugin_updates
                        SET plugin_id = (
                            SELECT plugins.id
                            FROM plugins
                            WHERE plugins.slug = plugin_updates.plugin_slug
                        )
                        WHERE plugin_id IS NULL
                        AND EXISTS (
                            SELECT 1
                            FROM plugins
                            WHERE plugins.slug = plugin_updates.plugin_slug
                        )
                    SQL);
                } else {
                    DB::table('plugin_updates')
                        ->join('plugins', 'plugin_updates.plugin_slug', '=', 'plugins.slug')
                        ->whereNull('plugin_updates.plugin_id')
                        ->update(['plugin_updates.plugin_id' => DB::raw('plugins.id')]);
                }
            }

            if (Schema::hasColumn('plugin_updates', 'version')) {
                DB::table('plugin_updates')
                    ->whereNull('current_version')
                    ->update(['current_version' => DB::raw('version')]);

                DB::table('plugin_updates')
                    ->whereNull('available_version')
                    ->update(['available_version' => DB::raw('version')]);
            }

            return;
        }

        Schema::create('plugin_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')->constrained('plugins')->cascadeOnDelete();
            $table->string('current_version', 50);
            $table->string('available_version', 50);
            $table->json('changelog')->nullable();
            $table->string('package_url')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->index(['plugin_id', 'available_version']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('plugin_updates') && (Schema::hasColumn('plugin_updates', 'plugin_slug') || Schema::hasColumn('plugin_updates', 'executed_at'))) {
            Schema::table('plugin_updates', function (Blueprint $table): void {
                if (Schema::hasColumn('plugin_updates', 'plugin_id')) {
                    $table->dropConstrainedForeignId('plugin_id');
                }

                foreach (['current_version', 'available_version', 'changelog', 'package_url', 'checked_at', 'installed_at'] as $column) {
                    if (Schema::hasColumn('plugin_updates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            return;
        }

        Schema::dropIfExists('plugin_updates');
    }
};
