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

        foreach (DB::table('lms_templates')->where('type', 'details')->get(['id', 'html_code']) as $template) {
            $html = (string) $template->html_code;

            if ($html === '' || str_contains($html, 'learn_tab')) {
                continue;
            }

            $html = preg_replace(
                '/(<button[^>]*data-course-tab="about"[^>]*>About<\/button>)/',
                '$1{{{learn_tab}}}',
                $html,
                1,
            ) ?? $html;
            $html = str_replace(
                '<section class="ainpa-course-tab-panel" data-course-panel="curriculum">',
                '{{{learn_panel}}}<section class="ainpa-course-tab-panel" data-course-panel="curriculum">',
                $html,
            );

            DB::table('lms_templates')->where('id', $template->id)->update([
                'html_code' => $html,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Template markup stays as edited by the administrator.
    }
};