<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Seo\Models\SeoSchemaTemplate;
use Modules\Seo\Services\SchemaTemplateValidator;
use Modules\Seo\Services\SeoSiteResolver;
use Throwable;

final class SchemasController extends Controller
{
    public function index(): View
    {
        $templates = null;

        try {
            $templates = SeoSchemaTemplate::query()
                ->orderByDesc('is_active')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->paginate(25);
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('seo::admin.schemas', compact('templates'));
    }

    public function create(): View
    {
        $schemaTemplate = new SeoSchemaTemplate([
            'is_active' => true,
            'priority' => 0,
            'schema_json' => "{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"WebPage\",\n  \"name\": \"\",\n  \"url\": \"\"\n}",
        ]);

        return view('seo::admin.schemas-form', compact('schemaTemplate'));
    }

    public function store(Request $request, SeoSiteResolver $sites): RedirectResponse
    {
        $siteId = $sites->id();
        $data = $this->validated($request, $siteId);

        SeoSchemaTemplate::query()->create(['site_id' => $siteId, ...$data]);

        return redirect()
            ->route('admin.plugins.seo.schemas.index')
            ->with('status', __('seo::ui.schema_saved'));
    }

    public function edit(SeoSchemaTemplate $schema): View
    {
        return view('seo::admin.schemas-form', ['schemaTemplate' => $schema]);
    }

    public function update(
        Request $request,
        SeoSchemaTemplate $schema,
        SeoSiteResolver $sites,
    ): RedirectResponse {
        $siteId = $sites->id();
        $data = $this->validated($request, $siteId, (int) $schema->id);

        $schema->fill($data)->save();

        return redirect()
            ->route('admin.plugins.seo.schemas.index')
            ->with('status', __('seo::ui.schema_saved'));
    }

    public function destroy(SeoSchemaTemplate $schema): RedirectResponse
    {
        $schema->delete();

        return redirect()
            ->route('admin.plugins.seo.schemas.index')
            ->with('status', __('seo::ui.schema_deleted'));
    }

    public function preview(Request $request, SchemaTemplateValidator $validator): View
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:120'],
            'module_slug' => ['nullable', 'string', 'max:120'],
            'route_pattern' => ['nullable', 'string', 'max:255'],
            'schema_json' => ['required', 'string'],
            'priority' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'record_id' => ['nullable', 'integer'],
        ]);

        $request->flash();
        $result = $validator->validate((string) $request->input('schema_json'));
        $recordId = (int) $request->input('record_id', 0);

        $schemaTemplate = new SeoSchemaTemplate([
            'name' => (string) $request->input('name', ''),
            'key' => (string) $request->input('key', ''),
            'module_slug' => $request->input('module_slug'),
            'route_pattern' => $request->input('route_pattern'),
            'schema_json' => (string) $request->input('schema_json'),
            'priority' => (int) $request->input('priority', 0),
            'is_active' => (bool) $request->input('is_active', false),
        ]);

        if ($recordId > 0) {
            $schemaTemplate->id = $recordId;
            $schemaTemplate->exists = true;
        }

        return view('seo::admin.schemas-form', [
            'schemaTemplate' => $schemaTemplate,
            'preview' => $result,
            'isPreview' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $siteId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'module_slug' => ['nullable', 'string', 'max:120'],
            'route_pattern' => ['nullable', 'string', 'max:255'],
            'schema_json' => ['required', 'string'],
            'priority' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $result = app(SchemaTemplateValidator::class)->validate((string) $data['schema_json']);

        if ($result['schema'] === null) {
            throw ValidationException::withMessages(['schema_json' => $result['errors']]);
        }

        $duplicate = SeoSchemaTemplate::query()
            ->where('site_id', $siteId)
            ->where('key', $data['key'])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['key' => __('seo::ui.schema_key_duplicate')]);
        }

        $type = $result['schema']['@type'] ?? null;
        if (is_array($type)) {
            $type = reset($type) ?: null;
        }

        return [
            'name' => $data['name'],
            'key' => $data['key'],
            'type' => is_string($type) ? $type : null,
            'module_slug' => ($data['module_slug'] ?? null) ?: null,
            'route_pattern' => ($data['route_pattern'] ?? null) ?: null,
            'schema_json' => json_encode($result['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
        ];
    }
}
