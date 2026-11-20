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

        foreach (['search', 'show'] as $key) {
            $row = DB::table('certificate_templates')->where('system_key', $key)->first();
            if ($row === null) {
                continue;
            }

            // Refresh only untouched system templates so administrator edits are preserved.
            if ($row->updated_by !== null) {
                continue;
            }

            DB::table('certificate_templates')->where('id', $row->id)->update([
                'html_code' => $this->resource($key, 'html'),
                'css_code' => $this->resource($key, 'css'),
                'js_code' => $this->resource($key, 'js'),
                'updated_at' => now(),
            ]);
        }
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
