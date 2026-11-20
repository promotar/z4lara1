<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibition_audit_entries', function (Blueprint $table): void {
            $table->string('idempotency_key', 191)->nullable()->unique('ex_audit_idempotency_uq')->after('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('exhibition_audit_entries', function (Blueprint $table): void {
            $table->dropUnique('ex_audit_idempotency_uq');
            $table->dropColumn('idempotency_key');
        });
    }
};
