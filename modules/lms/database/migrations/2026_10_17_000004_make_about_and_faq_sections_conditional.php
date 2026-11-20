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

        foreach (DB::table('lms_templates')->where('type', 'details')->get(['id', 'html_code', 'css_code']) as $template) {
            $html = (string) $template->html_code;
            $css = (string) $template->css_code;
            $originalHtml = $html;
            $originalCss = $css;

            if ($html !== '' && strpos($html, 'about_cards') === false) {
                $html = preg_replace(
                    '/(<section class="ainpa-course-tab-panel is-active" data-course-panel="about">\s*<div class="ainpa-course-card-grid">)[\s\S]*?(<\/div>\s*<\/section>)/',
                    '$1{{{about_cards}}}$2',
                    $html,
                    1,
                ) ?? $html;
            }

            if ($html !== '' && strpos($html, 'faq_tab') === false) {
                $html = preg_replace('/<button type="button" data-course-tab="faq">FAQ<\/button>/', '{{{faq_tab}}}', $html, 1) ?? $html;
            }

            if ($html !== '' && strpos($html, 'faq_panel') === false) {
                $html = preg_replace(
                    '/(<section class="ainpa-course-tab-panel" data-course-panel="faq">)[\s\S]*?(<\/section>)/',
                    '{{{faq_panel}}}',
                    $html,
                    1,
                ) ?? $html;
            }

            $css = preg_replace(
                '/\[data-lms-template="default-details"\] \.ainpa-course-faq-list:empty::before,\s*\[data-lms-template="default-details"\] \.ainpa-course-check-list:empty::before\s*\{[^}]*\}/',
                '',
                $css,
            ) ?? $css;

            if ($html !== $originalHtml || $css !== $originalCss) {
                DB::table('lms_templates')->where('id', $template->id)->update([
                    'html_code' => $html,
                    'css_code' => $css,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Template markup stays as edited by the administrator.
    }
};
