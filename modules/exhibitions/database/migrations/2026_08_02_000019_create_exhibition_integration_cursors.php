<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibition_integration_cursors', function (Blueprint $table): void {
            $table->string('integration_key', 100)->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamp('last_processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_integration_cursors');
    }
};
