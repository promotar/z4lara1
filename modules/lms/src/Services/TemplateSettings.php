<?php

namespace Modules\Lms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Lms\Models\Template;

class TemplateSettings
{
    public const CONTEXTS = [
        'catalog' => 'Courses Catalog',
        'details' => 'Course Details',
    ];

    public function templateFor(string $context): ?Template
    {
        if (! isset(self::CONTEXTS[$context]) || ! Schema::hasTable('lms_templates')) {
            return null;
        }

        $selectedId = Schema::hasTable('lms_template_settings')
            ? DB::table('lms_template_settings')->where('context', $context)->value('template_id')
            : null;
        $selected = $selectedId
            ? Template::query()->active()->where('type', $context)->find($selectedId)
            : null;

        return $selected ?: Template::query()
            ->active()
            ->where('type', $context)
            ->where('system_key', $context)
            ->first();
    }

    public function catalogTemplate(): ?Template
    {
        return $this->templateFor('catalog');
    }

    public function detailsTemplate(): ?Template
    {
        return $this->templateFor('details');
    }

    public function setCatalogTemplate(Template $template, ?int $userId = null): void
    {
        $this->setTemplate('catalog', $template, $userId);
    }

    public function setDetailsTemplate(Template $template, ?int $userId = null): void
    {
        $this->setTemplate('details', $template, $userId);
    }

    public function selectedCatalogId(): ?int
    {
        return $this->catalogTemplate()?->id;
    }

    public function selectedDetailsId(): ?int
    {
        return $this->detailsTemplate()?->id;
    }

    private function setTemplate(string $context, Template $template, ?int $userId = null): void
    {
        abort_unless(
            isset(self::CONTEXTS[$context]) && $template->type === $context && $template->status === 'active',
            422,
            'Only an active '.(self::CONTEXTS[$context] ?? 'LMS').' template can be selected.',
        );

        DB::table('lms_template_settings')->updateOrInsert(
            ['context' => $context],
            ['template_id' => $template->id, 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
