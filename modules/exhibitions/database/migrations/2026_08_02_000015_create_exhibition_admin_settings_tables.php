<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('group', 60)->index();
            $table->json('value');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
        });

        Schema::create('exhibition_setting_history', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 120)->index();
            $table->unsignedInteger('version');
            $table->json('previous_value')->nullable();
            $table->json('new_value');
            $table->unsignedBigInteger('actor_user_id');
            $table->text('reason');
            $table->timestamp('created_at');
            $table->unique(['setting_key', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_setting_history');
        Schema::dropIfExists('exhibition_settings');
    }
};
