<?php

namespace Modules\Blog\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Blog\Models\Template;
use Modules\Blog\Services\TemplateSettings;

class SettingsController extends Controller
{
    public function edit(TemplateSettings $settings): View
    {
        return view('blog::admin.settings.edit', [
            'contexts' => TemplateSettings::CONTEXTS,
            'selections' => $settings->selections(),
            'templates' => Template::query()->active()->orderByDesc('is_system')->orderBy('name')->get()->groupBy('category'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = collect(TemplateSettings::CONTEXTS)->mapWithKeys(fn (string $label, string $context): array => [
            $context => ['required', 'integer', Rule::exists('blog_templates', 'id')->where(fn ($query) => $query->where('status', 'active')->where('category', $context))],
        ])->all();
        $validated = $request->validate($rules);
        DB::transaction(function () use ($validated, $request): void {
            foreach ($validated as $context => $templateId) {
                DB::table('blog_template_settings')->updateOrInsert(
                    ['context' => $context],
                    ['template_id' => $templateId, 'updated_by' => $request->user()?->id, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });
        return back()->with('status', 'Blog templates updated.');
    }
}
