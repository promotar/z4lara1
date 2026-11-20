<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_artworks') || Schema::hasColumn('exhibition_artworks', 'slug')) {
            return;
        }

        Schema::table('exhibition_artworks', static function (Blueprint $table): void {
            $table->string('slug', 190)->nullable()->unique()->after('title');
        });

        DB::table('exhibition_artworks')->where('state', 'published')->orderBy('id')->each(function (object $artwork): void {
            $base = Str::slug((string) $artwork->title);
            if ($base === '') {
                $base = trim((string) preg_replace('/[^\pL\pN]+/u', '-', Str::lower((string) $artwork->title)), '-');
            }
            $base = Str::limit($base ?: 'artwork', 170, '');
            $candidate = $base;
            if (DB::table('exhibition_artworks')->where('slug', $candidate)->exists()) {
                $candidate = $base.'-'.Str::lower(substr(str_replace('-', '', (string) $artwork->public_id), 0, 8));
            }

            DB::table('exhibition_artworks')->where('id', $artwork->id)->update(['slug' => $candidate]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exhibition_artworks') || ! Schema::hasColumn('exhibition_artworks', 'slug')) {
            return;
        }

        Schema::table('exhibition_artworks', static function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
