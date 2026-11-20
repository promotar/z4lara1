<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_login_settings')) {
            Schema::create('google_login_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->boolean('allow_registration')->default(true);
                $table->string('client_id')->nullable();
                $table->text('client_secret_encrypted')->nullable();
                $table->string('hosted_domain')->nullable();
                $table->string('button_text', 80)->default('Continue with Google');
                $table->timestamps();
            });

            DB::table('google_login_settings')->insert([
                'id' => 1,
                'enabled' => false,
                'allow_registration' => true,
                'button_text' => 'Continue with Google',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('google_login_accounts')) {
            Schema::create('google_login_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('google_subject', 255)->unique();
                $table->string('google_email');
                $table->string('display_name')->nullable();
                $table->text('avatar_url')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_login_accounts');
        Schema::dropIfExists('google_login_settings');
    }
};
