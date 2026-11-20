<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may leave this new table behind when its first DDL attempt
        // fails before Laravel records the migration. Never replace a table
        // containing data.
        if (Schema::hasTable('exhibition_application_supporting_documents')) {
            if (DB::table('exhibition_application_supporting_documents')->exists()) {
                throw new RuntimeException('Refusing to replace a supporting-documents table that contains data.');
            }

            Schema::drop('exhibition_application_supporting_documents');
        }

        Schema::create('exhibition_application_supporting_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('application_id');
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamps();

            $table->foreign('application_id', 'exhibition_support_docs_application_fk')
                ->references('id')->on('exhibition_artist_applications')->cascadeOnDelete();
            $table->index(['application_id', 'id'], 'exhibition_supporting_documents_application_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_application_supporting_documents');
    }
};
