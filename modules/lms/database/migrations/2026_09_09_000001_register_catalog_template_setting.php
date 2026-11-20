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

        $templateId = DB::table('lms_templates')
            ->where('type', 'catalog')
            ->where('status', 'active')
            ->where('system_key', 'catalog')
            ->value('id');

        if (! $templateId || DB::table('lms_template_settings')->where('context', 'catalog')->exists()) {
            return;
        }

        DB::table('lms_template_settings')->insert([
            'context' => 'catalog',
            'template_id' => $templateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_templates') || ! Schema::hasTable('lms_template_settings')) {
            return;
        }

        $defaultTemplateId = DB::table('lms_templates')
            ->where('system_key', 'catalog')
            ->value('id');

        if ($defaultTemplateId) {
            DB::table('lms_template_settings')
                ->where('context', 'catalog')
                ->where('template_id', $defaultTemplateId)
                ->delete();
        }
    }
};
