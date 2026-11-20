<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/../../src/Support/CertificateNameNormalizer.php';

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificates') && ! Schema::hasColumn('certificates', 'name_normalized')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->string('name_normalized', 191)->nullable()->after('name');
                $table->index('name_normalized');
            });
        }

        if (Schema::hasTable('certificates') && Schema::hasColumn('certificates', 'name_normalized')) {
            DB::table('certificates')->select('id', 'name')->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('certificates')->where('id', $row->id)->update([
                        'name_normalized' => \Modules\Certificates\Support\CertificateNameNormalizer::normalize($row->name),
                    ]);
                }
            }, 'id');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificates') && Schema::hasColumn('certificates', 'name_normalized')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->dropIndex(['name_normalized']);
                $table->dropColumn('name_normalized');
            });
        }
    }
};
