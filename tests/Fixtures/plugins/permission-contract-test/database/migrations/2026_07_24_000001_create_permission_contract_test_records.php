<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_contract_test_records', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('permission_contract_test_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('permission_contract_test_note');
        });

        Schema::dropIfExists('permission_contract_test_records');
    }
};
