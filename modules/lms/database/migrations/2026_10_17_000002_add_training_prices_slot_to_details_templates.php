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

            if ($html === '' || str_contains($html, 'training_prices')) {
                continue;
            }

            $updated = preg_replace(
                '/(<div class="ainpa-course-price-line">.*?<\/div>)/s',
                '$1{{{training_prices}}}',
                $html,
                1,
                $count,
            );

            if ($count > 0 && is_string($updated) && $updated !== $html) {
                DB::table('lms_templates')->where('id', $template->id)->update([
                    'html_code' => $updated,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Template markup is user-editable; the injected slot is left in place.
    }
};
