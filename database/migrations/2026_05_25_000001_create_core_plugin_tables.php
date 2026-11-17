<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('version');
            $table->string('provider');
            $table->string('status')->default('uploaded')->index();
            $table->string('source_path')->nullable();
            $table->string('installed_path')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plugin_updates', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_slug')->index();
            $table->string('version');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->unique(['plugin_slug', 'version']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('properties_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('plugin_updates');
        Schema::dropIfExists('plugins');
    }
};
