<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('lms_templates')) {
            return;
        }

        foreach (DB::table('lms_templates')->where('type', 'catalog')->get(['id', 'css_code']) as $template) {
            $css = (string) $template->css_code;
            $count = 0;
            $updated = preg_replace('/(\.z4-course-card__media\s+img\s*\{[^}]*?object-fit:\s*)contain/i', '${1}cover', $css, -1, $count);

            if ($count > 0 && is_string($updated) && $updated !== $css) {
                DB::table('lms_templates')->where('id', $template->id)->update([
                    'css_code' => $updated,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Template styling stays as configured by the administrator.
    }
};