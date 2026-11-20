<?php

namespace Modules\Certificates\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Certificates\Models\CertificateTemplate;
use Modules\Certificates\Services\CertificateTemplateSettings;

class AdminTemplateController extends Controller
{
    public function __construct(private readonly CertificateTemplateSettings $settings) {}

    public function index(): View
    {
        return $this->editor(new CertificateTemplate([
            'type' => 'search',
            'status' => 'active',
            'html_code' => $this->starterHtml(),
            'css_code' => $this->starterCss(),
        ]));
    }

    public function edit(CertificateTemplate $template): View
    {
        return $this->editor($template);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request);
        $attributes['created_by'] = $request->user()?->id;
        $attributes['updated_by'] = $request->user()?->id;
        $template = CertificateTemplate::query()->create($attributes);

        return redirect(route('admin.plugins.certificates.templates.edit', $template, false))->with('status', __('certificates::message.template_created'));
    }

    public function update(Request $request, CertificateTemplate $template): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request, $template);
        $attributes['updated_by'] = $request->user()?->id;
        $template->update($attributes);

        return redirect(route('admin.plugins.certificates.templates.edit', $template, false))->with('status', __('certificates::message.template_saved'));
    }

    public function destroy(CertificateTemplate $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403, 'Default certificate templates cannot be deleted, but their code can be edited.');
        $template->delete();

        return redirect(route('admin.plugins.certificates.templates.index', [], false))->with('status', __('certificates::message.template_deleted'));
    }

    public function duplicate(Request $request, CertificateTemplate $template): RedirectResponse
    {
        $copy = $template->replicate(['is_system', 'system_key', 'created_by', 'updated_by']);
        $copy->name = $template->name.' Copy';
        $copy->slug = $this->uniqueSlug($template->slug.'-copy');
        $copy->is_system = false;
        $copy->system_key = null;
        $copy->created_by = $request->user()?->id;
        $copy->updated_by = $request->user()?->id;
        $copy->save();

        return redirect(route('admin.plugins.certificates.templates.edit', $copy, false))->with('status', __('certificates::message.template_copied'));
    }

    public function setDefault(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_unless(in_array($template->type, ['search', 'show'], true), 422);
        $this->settings->setTemplate($template->type, $template, $request->user()?->id);

        return redirect(route('admin.plugins.certificates.templates.edit', $template, false))
            ->with('status', __('certificates::message.template_default_set', ['type' => $template->type]));
    }

    private function editor(CertificateTemplate $template): View
    {
        return view('certificates::admin.templates.editor', [
            'template' => $template,
            'templates' => CertificateTemplate::query()->latest('updated_at')->paginate(20, ['*'], 'templates_page')->withQueryString(),
            'tokenGroups' => $this->tokenReference(),
            'selectedIds' => [
                'search' => $this->settings->selectedId('search'),
                'show' => $this->settings->selectedId('show'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, ?CertificateTemplate $template = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('certificate_templates', 'slug')->ignore($template?->id)],
            'type' => ['required', Rule::in(['search', 'show'])],
            'status' => ['required', Rule::in(['active', 'draft'])],
            'html_code' => ['nullable', 'string', 'max:1000000'],
            'css_code' => ['nullable', 'string', 'max:1000000'],
            'js_code' => ['nullable', 'string', 'max:1000000'],
        ]);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['name'], $template?->id);

        if ($template?->isSystem()) {
            $validated['slug'] = $template->slug;
            $validated['type'] = $template->type;
            $validated['status'] = 'active';
        }

        return $validated;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'certificate-template';
        $slug = $base;
        $suffix = 2;

        while (CertificateTemplate::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array<string, array<int, array{token:string, description:string}>> */
    private function tokenReference(): array
    {
        return [
            'Search page' => [
                ['token' => '{{{search_form}}}', 'description' => 'Ready-to-use search form (number + full name + CSRF). Use this unless you build your own form.'],
                ['token' => '{{heading}}', 'description' => 'Search page heading.'],
                ['token' => '{{intro}}', 'description' => 'Search page intro text.'],
                ['token' => '{{lookup_url}}', 'description' => 'POST target for a custom lookup form.'],
                ['token' => '{{{csrf_field}}}', 'description' => 'CSRF hidden input. Required inside a custom form.'],
                ['token' => '{{number_label}}', 'description' => 'Certificate number field label.'],
                ['token' => '{{number_help}}', 'description' => 'Helper text under the number field.'],
                ['token' => '{{or_label}}', 'description' => 'The "or" divider label.'],
                ['token' => '{{name_label}}', 'description' => 'Full-name field label.'],
                ['token' => '{{submit_label}}', 'description' => 'Submit button label.'],
                ['token' => '{{home_url}}', 'description' => 'Platform home URL.'],
                ['token' => '{{home_label}}', 'description' => 'Back-to-home link text.'],
            ],
            'Certificate page' => [
                ['token' => '{{name}}', 'description' => 'Certificate holder name.'],
                ['token' => '{{number_label}}', 'description' => 'Label shown before the certificate number.'],
                ['token' => '{{date_label}}', 'description' => 'Label shown before the issue date.'],
                ['token' => '{{slug}}', 'description' => 'Certificate number (slug).'],
                ['token' => '{{date}}', 'description' => 'Formatted date (Y-m-d).'],
                ['token' => '{{date_iso}}', 'description' => __('certificates::token.date_description')],
                ['token' => '{{image_url}}', 'description' => 'Signed image URL (valid 10 minutes).'],
                ['token' => '{{{image_tag}}}', 'description' => __('certificates::token.image_description')],
                ['token' => '{{search_url}}', 'description' => 'Certificate search page URL.'],
                ['token' => '{{search_label}}', 'description' => 'Back-to-search link text.'],
                ['token' => '{{page_title}}', 'description' => 'Page title (certificate name).'],
                ['token' => '{{home_url}}', 'description' => 'Platform home URL.'],
            ],
        ];
    }

    private function starterHtml(): string
    {
        return <<<'HTML'
<section class="search-panel">
    <h1>{{heading}}</h1>
    <p class="certificate-intro">{{intro}}</p>
    {{{search_form}}}
</section>
HTML;
    }

    private function starterCss(): string
    {
        return <<<'CSS'
/* Add page-specific styles here. The shared certificate stylesheet is already loaded. */
CSS;
    }
}
