<?php

namespace Modules\Blog\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Template;

class TemplateSettings
{
    public const CONTEXTS = ['single' => 'Single Post', 'archive' => 'Blog Archive', 'category' => 'Category Archive', 'search' => 'Search Results'];

    public function templateFor(string $context): ?Template
    {
        if (! isset(self::CONTEXTS[$context]) || ! Schema::hasTable('blog_templates')) {
            return null;
        }

        $selectedId = Schema::hasTable('blog_template_settings')
            ? DB::table('blog_template_settings')->where('context', $context)->value('template_id')
            : null;
        $selected = $selectedId ? Template::query()->active()->find($selectedId) : null;

        return $selected ?: Template::query()->active()->where('system_key', $context)->first();
    }

    public function selections(): array
    {
        return collect(array_keys(self::CONTEXTS))->mapWithKeys(fn (string $context): array => [$context => $this->templateFor($context)?->id])->all();
    }
}
