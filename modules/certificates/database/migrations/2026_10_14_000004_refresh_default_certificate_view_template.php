<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_templates')) {
            return;
        }

        $id = DB::table('certificate_templates')->where('system_key', 'show')->value('id');
        if (! $id) {
            return;
        }

        DB::table('certificate_templates')->where('id', $id)->update([
            'html_code' => $this->resource('show', 'html'),
            'css_code' => $this->resource('show', 'css'),
            'js_code' => $this->resource('show', 'js'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }

    private function resource(string $key, string $extension): string
    {
        $path = dirname(__DIR__, 2).'/resources/default-templates/'.$key.'.'.$extension;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
};
