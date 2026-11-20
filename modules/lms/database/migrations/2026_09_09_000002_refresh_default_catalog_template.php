<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_templates')) {
            return;
        }

        $templateId = DB::table('lms_templates')->where('system_key', 'catalog')->value('id');
        if (! $templateId) {
            return;
        }

        DB::table('lms_templates')->where('id', $templateId)->update([
            'html_code' => $this->resource('html'),
            'css_code' => $this->resource('css'),
            'js_code' => $this->resource('js'),
            'status' => 'active',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve template edits when rolling back application code.
    }

    private function resource(string $extension): string
    {
        $path = dirname(__DIR__, 2).'/resources/default-templates/catalog.'.$extension;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
};
