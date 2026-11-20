<?php

namespace Modules\Certificates\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Certificates\Models\CertificateTemplate;

final class CertificateTemplateSettings
{
    public const CONTEXTS = [
        'search' => 'Certificate Search',
        'show' => 'Certificate View',
    ];

    public function templateFor(string $context): ?CertificateTemplate
    {
        if (! isset(self::CONTEXTS[$context]) || ! Schema::hasTable('certificate_templates')) {
            return null;
        }

        $selectedId = Schema::hasTable('certificate_template_settings')
            ? DB::table('certificate_template_settings')->where('context', $context)->value('template_id')
            : null;
        $selected = $selectedId
            ? CertificateTemplate::query()->active()->where('type', $context)->find($selectedId)
            : null;

        return $selected ?: CertificateTemplate::query()
            ->active()
            ->where('type', $context)
            ->where('system_key', $context)
            ->first();
    }

    public function selectedId(string $context): ?int
    {
        return $this->templateFor($context)?->id;
    }

    public function setTemplate(string $context, CertificateTemplate $template, ?int $userId = null): void
    {
        abort_unless(
            isset(self::CONTEXTS[$context]) && $template->type === $context && $template->status === 'active',
            422,
            'Only an active matching certificate template can be selected.',
        );

        DB::table('certificate_template_settings')->updateOrInsert(
            ['context' => $context],
            ['template_id' => $template->id, 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
