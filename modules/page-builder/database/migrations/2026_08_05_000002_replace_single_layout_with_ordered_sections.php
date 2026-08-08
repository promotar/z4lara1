<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_pages')) {
            return;
        }

        if (! Schema::hasTable('vvvebjs_layout_sections')) {
            Schema::create('vvvebjs_layout_sections', function (Blueprint $table): void {
                $table->id();
                $table->string('placement', 20)->index();
                $table->foreignId('page_id')->constrained('platform_pages')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
                $table->index(['placement', 'sort_order']);
            });
        }

        $this->migrateSingleSelection();
        Schema::dropIfExists('vvvebjs_theme_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('vvvebjs_layout_sections');
    }

    private function migrateSingleSelection(): void
    {
        if (! Schema::hasTable('vvvebjs_theme_settings') || DB::table('vvvebjs_layout_sections')->exists()) {
            return;
        }

        $selection = DB::table('vvvebjs_theme_settings')->where('id', 1)->first();

        foreach (['header' => $selection->header_page_id ?? null, 'footer' => $selection->footer_page_id ?? null] as $placement => $pageId) {
            if ($pageId && DB::table('platform_pages')->where('id', $pageId)->exists()) {
                DB::table('vvvebjs_layout_sections')->insert([
                    'placement' => $placement,
                    'page_id' => $pageId,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
