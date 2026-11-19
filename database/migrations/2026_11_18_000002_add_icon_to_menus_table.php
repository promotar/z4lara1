<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('menus', 'icon')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            $table->string('icon', 64)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('menus', 'icon')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
