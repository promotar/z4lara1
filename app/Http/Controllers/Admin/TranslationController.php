<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Localization\TranslationFileEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TranslationController extends Controller
{
    public function __construct(private readonly TranslationFileEditor $editor) {}

    public function index(): View
    {
        return view('admin.translations.index', [
            'files' => $this->editor->listFiles(),
            'locales' => $this->editor->localeLabels(),
            'moduleLabels' => $this->editor->moduleLabels(),
        ]);
    }

    public function edit(Request $request, string $locale, string $group): View
    {
        $module = $request->query('module');
        $module = is_string($module) && $module !== '' ? $module : null;

        abort_unless($this->editor->isAvailableLocale($locale), 404);
        abort_unless($this->editor->isValidGroup($group), 404);

        if ($module !== null && $this->editor->pathFor($locale, $group, $module) === null) {
            abort(404);
        }

        abort_unless($this->editor->groupExists($locale, $group, $module), 404);

        $otherLocale = $locale === 'en' ? 'ar' : 'en';

        return view('admin.translations.edit', [
            'locale' => $locale,
            'group' => $group,
            'module' => $module,
            'otherLocale' => $otherLocale,
            'content' => $this->editor->rawContent($locale, $group, $module) ?? '',
            'path' => $this->editor->pathFor($locale, $group, $module),
        ]);
    }

    public function raw(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:16'],
            'group' => ['required', 'string', 'max:64'],
            'module' => ['nullable', 'string', 'max:64'],
            'content' => ['required', 'string', 'max:2000000'],
        ]);

        abort_unless($this->editor->isValidGroup($data['group']), 404);

        $module = isset($data['module']) && $data['module'] !== '' ? $data['module'] : null;

        if ($module !== null && $this->editor->pathFor($data['locale'], $data['group'], $module) === null) {
            abort(404);
        }

        $saved = $this->editor->saveRawContent($data['locale'], $data['group'], $module, $data['content']);

        return back()->with('status', $saved
            ? __('core.translations.saved_raw')
            : __('core.translations.invalid_text'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:16'],
            'group' => ['required', 'string', 'max:64'],
            'module' => ['nullable', 'string', 'max:64'],
            'keys' => ['required', 'array', 'max:5000'],
            'keys.*' => ['string', 'max:255'],
            'values' => ['required', 'array', 'max:5000'],
            'values.*' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_unless($this->editor->isValidGroup($data['group']), 404);

        $module = isset($data['module']) && $data['module'] !== '' ? $data['module'] : null;

        if ($module !== null && $this->editor->pathFor($data['locale'], $data['group'], $module) === null) {
            abort(404);
        }

        $values = [];

        foreach ($data['keys'] as $index => $key) {
            $values[$key] = $data['values'][$index] ?? '';
        }

        $saved = $this->editor->saveGroup($data['locale'], $data['group'], $values, $module);

        return redirect()
            ->route('admin.translations.edit', array_filter([
                'locale' => $data['locale'],
                'group' => $data['group'],
                'module' => $module,
            ]))
            ->with('status', $saved > 0
                ? __('core.translations.saved_count', ['count' => $saved])
                : __('core.translations.nothing_saved'));
    }

    public function storeLanguage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}(?:-[A-Z]{2})?$/'],
            'label' => ['required', 'string', 'max:64'],
            'file' => ['required', 'file', 'mimes:txt', 'max:2048'],
            'module' => ['nullable', 'string', 'max:64'],
        ]);

        $code = $data['code'];
        $module = isset($data['module']) && $data['module'] !== '' ? $data['module'] : null;

        if ($module !== null && (preg_match('/^[a-z0-9_\-]+$/', $module) !== 1 || ! is_dir(base_path('modules/'.$module)))) {
            abort(404);
        }

        $content = (string) file_get_contents($request->file('file')->getRealPath());
        $saved = $this->editor->createLocale($code, $data['label'], 'en', $content, null, $module);

        if (! $saved) {
            return back()->with('status', __('core.translations.language_failed'));
        }

        return back()->with('status', __('core.translations.language_added'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keys' => ['required', 'array', 'max:500'],
            'keys.*' => ['string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $translations = [];

        foreach (array_unique($data['keys']) as $key) {
            $target = $this->editor->resolve($key, 'ar');

            $translations[$key] = [
                'en' => $this->editor->value($key, 'en'),
                'ar' => $this->editor->value($key, 'ar'),
                'source' => $target['path'] ?? null,
            ];
        }

        return response()->json([
            'locale' => $data['locale'] ?? app()->getLocale(),
            'translations' => $translations,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:16'],
            'value' => ['required', 'string', 'max:5000'],
        ]);

        $saved = $this->editor->save($data['key'], $data['locale'], $data['value']);

        if ($saved === null) {
            return response()->json([
                'message' => __('core.translate.error'),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'key' => $saved['key'],
            'locale' => $saved['locale'],
            'value' => $saved['value'],
            'message' => __('core.translate.saved'),
        ]);
    }
}

