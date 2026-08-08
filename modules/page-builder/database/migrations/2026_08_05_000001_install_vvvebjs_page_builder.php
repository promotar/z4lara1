<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_pages')) {
            return;
        }

        $this->addPageColumns();
        $this->migrateFrontBuilderPages();
        $this->migrateThemeTemplates();
        $this->createVvvebStorage();
        $this->migrateDocuments();
        $this->migrateThemeSelection();
        $this->dropOldBuilders();
    }

    public function down(): void
    {
        Schema::dropIfExists('vvvebjs_page_revisions');
        Schema::dropIfExists('vvvebjs_layout_sections');

        if (Schema::hasTable('platform_pages') && Schema::hasColumn('platform_pages', 'vvvebjs_html')) {
            Schema::table('platform_pages', fn (Blueprint $table) => $table->dropColumn('vvvebjs_html'));
        }
    }

    private function addPageColumns(): void
    {
        Schema::table('platform_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_pages', 'content_type')) {
                $table->string('content_type', 40)->default('page')->index();
            }
            if (! Schema::hasColumn('platform_pages', 'block_key')) {
                $table->string('block_key')->nullable()->index();
            }
            if (! Schema::hasColumn('platform_pages', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->index();
            }
            if (! Schema::hasColumn('platform_pages', 'category')) {
                $table->string('category', 120)->nullable()->index();
            }
            if (! Schema::hasColumn('platform_pages', 'menu_label')) {
                $table->string('menu_label')->nullable();
            }
            if (! Schema::hasColumn('platform_pages', 'show_in_menu')) {
                $table->boolean('show_in_menu')->default(false)->index();
            }
            if (! Schema::hasColumn('platform_pages', 'sort_order')) {
                $table->integer('sort_order')->default(0)->index();
            }
            if (! Schema::hasColumn('platform_pages', 'html')) {
                $table->longText('html')->nullable();
            }
            if (! Schema::hasColumn('platform_pages', 'css')) {
                $table->longText('css')->nullable();
            }
            if (! Schema::hasColumn('platform_pages', 'vvvebjs_html')) {
                $table->longText('vvvebjs_html')->nullable();
            }
        });
    }

    private function createVvvebStorage(): void
    {
        // Compatibility ownership bridge for Page Builder 2.2.0 updates. Its
        // values are migrated below, then this legacy table is removed.
        if (! Schema::hasTable('page_builder_theme_settings')) {
            Schema::create('page_builder_theme_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->unsignedBigInteger('header_page_id')->nullable();
                $table->unsignedBigInteger('footer_page_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vvvebjs_page_revisions')) {
            Schema::create('vvvebjs_page_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('page_id')->constrained('platform_pages')->cascadeOnDelete();
                $table->string('title');
                $table->longText('vvvebjs_html')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['page_id', 'created_at']);
            });
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
    }

    private function migrateDocuments(): void
    {
        foreach (DB::table('platform_pages')->whereNull('vvvebjs_html')->get(['id', 'title', 'html', 'content', 'css']) as $page) {
            $title = htmlspecialchars((string) $page->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body = (string) ($page->html ?: $page->content ?: '');
            $css = (string) ($page->css ?? '');
            $document = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<base href="/page-builder-assets/demo/landing/"><title>'.$title.'</title>'
                .'<link href="css/style.bundle.css" rel="stylesheet"><link href="css/custom.css" rel="stylesheet">'
                .'<style>'.$css.'</style></head><body class="page">'.$body.'</body></html>';
            DB::table('platform_pages')->where('id', $page->id)->update(['vvvebjs_html' => $document, 'html' => $body]);
        }
    }

    private function migrateFrontBuilderPages(): void
    {
        if (! Schema::hasTable('front_builder_pages')) {
            return;
        }

        $idMap = [];
        foreach (DB::table('front_builder_pages')->orderBy('id')->get() as $legacy) {
            $slug = $this->uniqueSlug((string) $legacy->slug);
            $now = now();
            $idMap[(int) $legacy->id] = DB::table('platform_pages')->insertGetId([
                'title' => $legacy->title,
                'slug' => $slug,
                'content_type' => 'page',
                'block_key' => null,
                'parent_id' => null,
                'category' => $legacy->category ?? null,
                'menu_label' => $legacy->menu_label ?? null,
                'show_in_menu' => (bool) ($legacy->show_in_menu ?? false),
                'content' => $legacy->html ?? null,
                'html' => $legacy->html ?? null,
                'css' => $legacy->css ?? null,
                'vvvebjs_html' => null,
                'status' => $legacy->status ?? 'draft',
                'sort_order' => (int) ($legacy->sort_order ?? 0),
                'seo_title' => null,
                'meta_description' => null,
                'published_at' => $legacy->published_at ?? null,
                'created_at' => $legacy->created_at ?? $now,
                'updated_at' => $legacy->updated_at ?? $now,
            ]);
        }

        foreach (DB::table('front_builder_pages')->whereNotNull('parent_id')->get(['id', 'parent_id']) as $legacy) {
            $pageId = $idMap[(int) $legacy->id] ?? null;
            $parentId = $idMap[(int) $legacy->parent_id] ?? null;
            if ($pageId && $parentId && $pageId !== $parentId) {
                DB::table('platform_pages')->where('id', $pageId)->update(['parent_id' => $parentId]);
            }
        }
    }

    private function migrateThemeTemplates(): void
    {
        if (! Schema::hasTable('platform_theme_builder_templates')) {
            return;
        }

        foreach (DB::table('platform_theme_builder_templates')->orderBy('id')->get() as $template) {
            $type = match ((string) $template->template_type) {
                'header' => 'header', 'footer' => 'footer', default => 'page',
            };
            $now = now();
            DB::table('platform_pages')->insert([
                'title' => $template->name,
                'slug' => $this->uniqueSlug((string) ($template->slug ?: $template->name)),
                'content_type' => $type,
                'block_key' => null,
                'parent_id' => null,
                'category' => 'migrated-to-vvvebjs',
                'menu_label' => null,
                'show_in_menu' => false,
                'content' => $template->html,
                'html' => $template->html,
                'css' => $template->css,
                'vvvebjs_html' => null,
                'status' => $template->status === 'published' ? 'published' : 'draft',
                'sort_order' => 0,
                'seo_title' => null,
                'meta_description' => $template->description,
                'published_at' => $template->status === 'published' ? $now : null,
                'created_at' => $template->created_at ?? $now,
                'updated_at' => $template->updated_at ?? $now,
            ]);
        }
    }

    private function migrateThemeSelection(): void
    {
        $selection = Schema::hasTable('page_builder_theme_settings') ? DB::table('page_builder_theme_settings')->where('id', 1)->first() : null;
        foreach (['header' => $selection->header_page_id ?? null, 'footer' => $selection->footer_page_id ?? null] as $placement => $pageId) {
            if ($pageId) {
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

    private function dropOldBuilders(): void
    {
        Schema::dropIfExists('front_builder_pages');
        Schema::dropIfExists('page_builder_theme_settings');
        Schema::dropIfExists('vvvebjs_theme_settings');
        Schema::dropIfExists('platform_page_revisions');
        Schema::dropIfExists('platform_theme_builder_template_conditions');
        Schema::dropIfExists('platform_theme_builder_templates');
        Schema::dropIfExists('platform_theme_builder_conditions');

        if (Schema::hasColumn('platform_pages', 'page_builder_json')) {
            Schema::table('platform_pages', fn (Blueprint $table) => $table->dropColumn('page_builder_json'));
        }
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $index = 2;
        while (DB::table('platform_pages')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }
};
