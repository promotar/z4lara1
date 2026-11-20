<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Re-runnable: the table may already exist on installations that were
        // provisioned with an external SQL import before this migration ran.
        if (Schema::hasTable('certificates')) {
            return;
        }

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->dateTime('date');
            $slug = $table->string('slug', 190)->unique();
            if (DB::connection()->getDriverName() === 'mysql') {
                $slug->collation('utf8mb4_bin');
            }
            $table->string('image');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
