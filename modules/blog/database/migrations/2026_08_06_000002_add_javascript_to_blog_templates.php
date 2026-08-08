<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_templates') || Schema::hasColumn('blog_templates', 'js_code')) {
            return;
        }

        Schema::table('blog_templates', function (Blueprint $table): void {
            $table->longText('js_code')->nullable()->after('css_code');
        });

        foreach (['single', 'archive', 'category', 'search', 'slider'] as $key) {
            $path = dirname(__DIR__, 2).'/resources/default-templates/'.$key.'.js';
            DB::table('blog_templates')
                ->where('system_key', $key)
                ->update(['js_code' => is_file($path) ? (string) file_get_contents($path) : null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_templates') && Schema::hasColumn('blog_templates', 'js_code')) {
            Schema::table('blog_templates', fn (Blueprint $table) => $table->dropColumn('js_code'));
        }
    }
};
