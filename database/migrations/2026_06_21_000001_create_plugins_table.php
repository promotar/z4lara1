<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plugins')) {
            Schema::table('plugins', function (Blueprint $table): void {
                if (! Schema::hasColumn('plugins', 'description')) {
                    $table->text('description')->nullable();
                }

                if (! Schema::hasColumn('plugins', 'author')) {
                    $table->string('author')->nullable();
                }

                if (! Schema::hasColumn('plugins', 'path')) {
                    $table->string('path')->nullable();
                }

                if (! Schema::hasColumn('plugins', 'manifest')) {
                    $table->json('manifest')->nullable();
                }

                if (! Schema::hasColumn('plugins', 'dependencies')) {
                    $table->json('dependencies')->nullable();
                }

                if (! Schema::hasColumn('plugins', 'activated_at')) {
                    $table->timestamp('activated_at')->nullable();
                }
            });

            return;
        }

        Schema::create('plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('version', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('status', 40)->default('installed')->index();
            $table->string('path')->nullable();
            $table->string('provider')->nullable();
            $table->json('manifest')->nullable();
            $table->json('dependencies')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('plugins') && (Schema::hasColumn('plugins', 'source_path') || Schema::hasColumn('plugins', 'settings_json'))) {
            Schema::table('plugins', function (Blueprint $table): void {
                foreach (['description', 'author', 'path', 'manifest', 'dependencies', 'activated_at'] as $column) {
                    if (Schema::hasColumn('plugins', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            return;
        }

        Schema::dropIfExists('plugins');
    }
};
