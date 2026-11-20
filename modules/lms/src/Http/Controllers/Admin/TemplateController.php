<?php

namespace Modules\Lms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Lms\Models\Template;
use Modules\Lms\Services\TemplateSettings;

class TemplateController extends Controller
{
    public function __construct(private readonly TemplateSettings $settings) {}

    public function index(): View
    {
        return $this->editor(new Template([
            'type' => 'catalog',
            'status' => 'active',
            'html_code' => $this->starterHtml(),
            'css_code' => $this->starterCss(),
        ]));
    }

    public function edit(Template $template): View
    {
        return $this->editor($template);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request);
        $attributes['created_by'] = $request->user()?->id;
        $attributes['updated_by'] = $request->user()?->id;
        $template = Template::query()->create($attributes);

        return redirect(route('admin.plugins.lms.templates.edit', $template, false))->with('status', 'Course template created.');
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request, $template);
        $attributes['updated_by'] = $request->user()?->id;
        $template->update($attributes);

        return redirect(route('admin.plugins.lms.templates.edit', $template, false))->with('status', 'Course template saved.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403, 'Default LMS templates cannot be deleted, but their code can be edited.');
        $template->delete();

        return redirect(route('admin.plugins.lms.templates.index', [], false))->with('status', 'Course template deleted.');
    }

    public function duplicate(Request $request, Template $template): RedirectResponse
    {
        $copy = $template->replicate(['is_system', 'system_key', 'created_by', 'updated_by']);
        $copy->name = $template->name.' Copy';
        $copy->slug = $this->uniqueSlug($template->slug.'-copy');
        $copy->is_system = false;
        $copy->system_key = null;
        $copy->created_by = $request->user()?->id;
        $copy->updated_by = $request->user()?->id;
        $copy->save();

        return redirect(route('admin.plugins.lms.templates.edit', $copy, false))->with('status', 'Template copied.');
    }

    public function setDefault(Request $request, Template $template): RedirectResponse
    {
        if ($template->type === 'catalog') {
            $this->settings->setCatalogTemplate($template, $request->user()?->id);

            return redirect(route('admin.plugins.lms.templates.edit', $template, false))->with('status', 'This template now renders the public Courses page.');
        }

        $this->settings->setDetailsTemplate($template, $request->user()?->id);

        return redirect(route('admin.plugins.lms.templates.edit', $template, false))->with('status', 'This template now renders every course details page.');
    }

    private function editor(Template $template): View
    {
        return view('lms::admin.templates.editor', [
            'template' => $template,
            'templates' => Template::query()->latest('updated_at')->paginate(20, ['*'], 'templates_page')->withQueryString(),
            'tokenGroups' => $this->tokenReference(),
            'selectedCatalogId' => $this->settings->selectedCatalogId(),
            'selectedDetailsId' => $this->settings->selectedDetailsId(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, ?Template $template = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('lms_templates', 'slug')->ignore($template?->id)],
            'type' => ['required', Rule::in(['catalog', 'details'])],
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
        $base = Str::slug($value) ?: 'course-template';
        $slug = $base;
        $suffix = 2;

        while (Template::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array<string, array<int, array{token:string, description:string}>> */
    private function tokenReference(): array
    {
        return [
            'Course content' => [
                ['token' => '{{#courses}}...{{/courses}}', 'description' => 'Repeat markup for every course. Used by Page Builder list elements.'],
                ['token' => '{{id}}', 'description' => 'Course database ID.'],
                ['token' => '{{title}}', 'description' => 'Course title.'],
                ['token' => '{{slug}}', 'description' => 'Course slug.'],
                ['token' => '{{url}}', 'description' => 'Public course details URL.'],
                ['token' => '{{subtitle}}', 'description' => 'Course subtitle.'],
                ['token' => '{{short_description}}', 'description' => 'Short course summary.'],
                ['token' => '{{description}}', 'description' => 'Full description as safe plain text.'],
                ['token' => '{{{description_html}}}', 'description' => 'Safe formatted course description.'],
                ['token' => '{{image_url}}', 'description' => 'Featured course image URL.'],
            ],
            'Course information' => [
                ['token' => '{{category}}', 'description' => 'Course category.'],
                ['token' => '{{level}}', 'description' => 'Course level.'],
                ['token' => '{{language}}', 'description' => 'Course language.'],
                ['token' => '{{instructor_name}}', 'description' => 'Instructor display name.'],
                ['token' => '{{instructor_title}}', 'description' => 'Instructor title.'],
                ['token' => '{{lesson_count}}', 'description' => 'Published lesson count.'],
                ['token' => '{{student_count}}', 'description' => 'Enrollment count.'],
                ['token' => '{{duration}}', 'description' => 'Calculated course duration.'],
                ['token' => '{{access_duration}}', 'description' => 'Saved access duration.'],
                ['token' => '{{rating}}', 'description' => 'Course rating shown on the details page. Details templates only.'],
                ['token' => '{{reviews_count}}', 'description' => 'Current learner/review count. Details templates only.'],
                ['token' => '{{updated_at}}', 'description' => 'Last course update date. Details templates only.'],
                ['token' => '{{instructor_initial}}', 'description' => 'First instructor initial for an avatar.'],
                ['token' => '{{instructor_profile_url}}', 'description' => 'Instructor public profile URL. Details templates only.'],
            ],
            'Pricing and actions' => [
                ['token' => '{{pricing_type}}', 'description' => 'free or paid.'],
                ['token' => '{{regular_price}}', 'description' => 'Regular price.'],
                ['token' => '{{sale_price}}', 'description' => 'Sale price.'],
                ['token' => '{{price}}', 'description' => 'Current numeric price.'],
                ['token' => '{{price_label}}', 'description' => 'Formatted price with currency or Free.'],
                ['token' => '{{currency}}', 'description' => 'Currency code.'],
                ['token' => '{{{action_button}}}', 'description' => 'Course details enrollment or player button. Details templates only.'],
                ['token' => '{{enrollment_status}}', 'description' => 'Current learner enrollment status. Details templates only.'],
                ['token' => '{{enrollment_status_class}}', 'description' => 'Adds is-enrolled when the learner owns the course. Details templates only.'],
                ['token' => '{{{course_curriculum}}}', 'description' => 'Functional curriculum with modules, lesson locking, previews, and player links. Details templates only.'],
                ['token' => '{{{promo_card}}}', 'description' => 'Featured image and promotional-video launcher. Details templates only.'],
            ],
            'Repeatable course data' => [
                ['token' => '{{#outcomes}}<li>{{text}}</li>{{/outcomes}}', 'description' => 'Repeat saved What You Will Learn rows.'],
                ['token' => '{{#requirements}}<li>{{text}}</li>{{/requirements}}', 'description' => 'Repeat course requirements.'],
                ['token' => '{{#audience}}<li>{{text}}</li>{{/audience}}', 'description' => 'Repeat target audience rows.'],
                ['token' => '{{#faqs}}<details><summary>{{question}}</summary><p>{{answer}}</p></details>{{/faqs}}', 'description' => 'Repeat course FAQs.'],
                ['token' => '{{#lessons}}<div>{{number}}. {{title}} · {{duration}}</div>{{/lessons}}', 'description' => 'Repeat published lessons.'],
            ],
            'Catalog and site' => [
                ['token' => '{{site_name}}', 'description' => 'Platform name.'],
                ['token' => '{{catalog_title}}', 'description' => 'Courses collection title.'],
                ['token' => '{{catalog_description}}', 'description' => 'Courses collection description.'],
                ['token' => '{{results_count}}', 'description' => 'Number of courses rendered.'],
                ['token' => '{{courses_url}}', 'description' => 'Main LMS courses URL.'],
                ['token' => '{{teach_url}}', 'description' => 'Instructor application URL. Catalog templates only.'],
                ['token' => '{{search_query}}', 'description' => 'Current catalog search query. Catalog templates only.'],
                ['token' => '{{{category_options}}}', 'description' => 'Category select options with the current value selected. Catalog templates only.'],
                ['token' => '{{{level_options}}}', 'description' => 'Level select options with the current value selected. Catalog templates only.'],
                ['token' => '{{{sort_options}}}', 'description' => 'Sort select options with the current value selected. Catalog templates only.'],
                ['token' => '{{{empty_state}}}', 'description' => 'Catalog empty-state markup when no course matches. Catalog templates only.'],
                ['token' => '{{{pagination_html}}}', 'description' => 'Server-rendered catalog pagination. Catalog templates only.'],
                ['token' => '{{home_url}}', 'description' => 'Platform home page URL.'],
            ],
        ];
    }

    private function starterHtml(): string
    {
        return <<<'HTML'
<section class="custom-course-grid">
    {{#courses}}
    <article class="custom-course-card">
        <img src="{{image_url}}" alt="{{title}}">
        <div><span>{{category}}</span><h2><a href="{{url}}">{{title}}</a></h2><p>{{short_description}}</p><strong>{{price_label}}</strong></div>
    </article>
    {{/courses}}
</section>
HTML;
    }

    private function starterCss(): string
    {
        return <<<'CSS'
.custom-course-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; padding:24px; }
.custom-course-card { overflow:hidden; border:1px solid #eadada; border-radius:14px; background:#fff; }
.custom-course-card img { display:block; width:100%; aspect-ratio:16/10; object-fit:cover; }
.custom-course-card div { padding:18px; }
.custom-course-card h2 { margin:8px 0; font-size:21px; }
.custom-course-card a { color:inherit; text-decoration:none; }
@media(max-width:800px){.custom-course-grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.custom-course-grid{grid-template-columns:1fr}}
CSS;
    }
}
