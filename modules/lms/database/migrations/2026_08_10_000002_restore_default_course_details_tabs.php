<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_templates') || ! Schema::hasTable('lms_template_settings')) {
            return;
        }

        $templateId = DB::table('lms_templates')->where('system_key', 'details')->value('id');
        $values = [
            'name' => 'Default Course Details',
            'slug' => 'default-details',
            'type' => 'details',
            'status' => 'active',
            'html_code' => $this->resource('html'),
            'css_code' => $this->resource('css'),
            'js_code' => $this->resource('js'),
            'is_system' => true,
            'system_key' => 'details',
            'updated_at' => now(),
        ];

        if ($templateId) {
            DB::table('lms_templates')->where('id', $templateId)->update($values);
        } else {
            $templateId = DB::table('lms_templates')->insertGetId($values + [
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
            ]);
        }

        // Restore the original tabbed system template as the active details
        // design without removing custom templates or the selection feature.
        DB::table('lms_template_settings')->updateOrInsert(
            ['context' => 'details'],
            ['template_id' => $templateId, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        // This migration changes only the bundled system template content.
        // Custom templates and LMS data must never be removed on rollback.
    }

    private function resource(string $extension): string
    {
        $path = dirname(__DIR__, 2).'/resources/default-templates/details.'.$extension;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
};
