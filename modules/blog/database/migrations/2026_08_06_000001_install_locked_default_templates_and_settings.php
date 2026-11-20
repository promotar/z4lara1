<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $defaults = [
        'single' => ['name' => 'Default Single Post', 'category' => 'single'],
        'archive' => ['name' => 'Default Archive', 'category' => 'archive'],
        'category' => ['name' => 'Default Category', 'category' => 'category'],
        'search' => ['name' => 'Default Search Results', 'category' => 'search'],
        'slider' => ['name' => 'Default Post Slider', 'category' => 'slider'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('blog_templates')) {
            return;
        }

        Schema::table('blog_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('blog_templates', 'is_system')) {
                $table->boolean('is_system')->default(false)->index();
            }
            if (! Schema::hasColumn('blog_templates', 'system_key')) {
                $table->string('system_key', 80)->nullable()->unique();
            }
        });

        if (! Schema::hasTable('blog_template_settings')) {
            Schema::create('blog_template_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('context', 40)->unique();
                $table->foreignId('template_id')->nullable()->constrained('blog_templates')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        foreach ($this->defaults as $key => $definition) {
            $id = DB::table('blog_templates')->where('system_key', $key)->value('id');
            $values = [
                'name' => $definition['name'], 'slug' => 'default-'.$key, 'category' => $definition['category'],
                'status' => 'active', 'html_code' => $this->resource($key, 'html'), 'css_code' => $this->resource($key, 'css'),
                'is_system' => true, 'system_key' => $key, 'updated_at' => now(),
            ];
            if ($id) {
                DB::table('blog_templates')->where('id', $id)->update($values);
            } else {
                $id = DB::table('blog_templates')->insertGetId($values + ['created_at' => now()]);
            }

            if ($key !== 'slider') {
                DB::table('blog_template_settings')->updateOrInsert(
                    ['context' => $key],
                    ['template_id' => $id, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_template_settings');
        if (! Schema::hasTable('blog_templates')) {
            return;
        }
        if (Schema::hasColumn('blog_templates', 'is_system')) {
            DB::table('blog_templates')->where('is_system', true)->delete();
        }
        Schema::table('blog_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('blog_templates', 'system_key')) {
                $table->dropUnique(['system_key']);
                $table->dropColumn('system_key');
            }
            if (Schema::hasColumn('blog_templates', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });
    }

    private function resource(string $key, string $extension): string
    {
        $path = dirname(__DIR__, 2).'/resources/default-templates/'.$key.'.'.$extension;
        return is_file($path) ? (string) file_get_contents($path) : '';
    }
};
