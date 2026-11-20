<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $defaults = [
        'search' => ['name' => 'Default Certificate Search', 'type' => 'search'],
        'show' => ['name' => 'Default Certificate View', 'type' => 'show'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('certificate_templates')) {
            Schema::create('certificate_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type', 40)->default('search')->index();
                $table->string('status', 20)->default('active')->index();
                $table->longText('html_code')->nullable();
                $table->longText('css_code')->nullable();
                $table->longText('js_code')->nullable();
                $table->boolean('is_system')->default(false)->index();
                $table->string('system_key', 80)->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('certificate_template_settings')) {
            Schema::create('certificate_template_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('context', 40)->unique();
                $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        foreach ($this->defaults as $key => $definition) {
            $templateId = DB::table('certificate_templates')->where('system_key', $key)->value('id');
            $values = [
                'name' => $definition['name'],
                'slug' => 'default-'.$key,
                'type' => $definition['type'],
                'status' => 'active',
                'html_code' => $this->resource($key, 'html'),
                'css_code' => $this->resource($key, 'css'),
                'js_code' => $this->resource($key, 'js'),
                'is_system' => true,
                'system_key' => $key,
                'updated_at' => now(),
            ];

            if ($templateId) {
                DB::table('certificate_templates')->where('id', $templateId)->update($values);
            } else {
                $templateId = DB::table('certificate_templates')->insertGetId($values + ['created_at' => now()]);
            }

            DB::table('certificate_template_settings')->updateOrInsert(
                ['context' => $key],
                ['template_id' => $templateId, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_template_settings');
        Schema::dropIfExists('certificate_templates');
    }

    private function resource(string $key, string $extension): string
    {
        $path = dirname(__DIR__, 2).'/resources/default-templates/'.$key.'.'.$extension;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
};
