<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seo\Services\SeoContentTypeRegistry;
use Modules\Seo\Services\SeoKeywordExtractor;
use Modules\Seo\Services\SeoModuleSettings;
use Throwable;

final class ModulesController extends Controller
{
    public function index(
        SeoContentTypeRegistry $types,
        SeoModuleSettings $settings,
        SeoKeywordExtractor $keywords,
        PlatformLocale $locales,
    ): View {
        $modules = [];
        $availableLocales = $locales->languages();
        $requestedLocale = request()->query('locale');
        $activeLocale = is_string($requestedLocale) && array_key_exists($requestedLocale, $availableLocales)
            ? $requestedLocale
            : $locales->current();

        if (! array_key_exists($activeLocale, $availableLocales)) {
            $activeLocale = $locales->defaultCode();
        }

        try {
            $groups = collect($types->all())->groupBy('module');

            foreach ($groups as $slug => $definitions) {
                $sampleKeywords = [];
                $firstType = (string) $definitions->keys()->first();
                $fieldsByLocale = [];

                foreach (array_keys($availableLocales) as $locale) {
                    $fieldsByLocale[$locale] = $settings->forModule((string) $slug, $locale);
                }

                try {
                    $sample = $firstType !== '' ? $types->records($firstType)?->first() : null;

                    if ($sample !== null) {
                        $text = implode(' ', array_filter([
                            (string) ($sample->title ?? ''),
                            (string) ($sample->short_description ?? ''),
                            (string) ($sample->seo_description ?? ''),
                            (string) ($sample->meta_description ?? ''),
                            strip_tags((string) ($sample->description ?? $sample->excerpt ?? $sample->content ?? '')),
                        ]));
                        $sampleKeywords = $keywords->extract($text, 3);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }

                $modules[] = [
                    'slug' => (string) $slug,
                    'label' => $settings->label((string) $slug),
                    'types' => $definitions->pluck('label')->filter()->unique()->values()->all(),
                    'fields' => $fieldsByLocale[$activeLocale] ?? $settings->defaults(),
                    'fields_by_locale' => $fieldsByLocale,
                    'sample_keywords' => $sampleKeywords,
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('seo::admin.modules', [
            'modules' => $modules,
            'fields' => SeoModuleSettings::FIELDS,
            'locales' => $availableLocales,
            'locale' => $activeLocale,
        ]);
    }

    public function update(
        Request $request,
        SeoContentTypeRegistry $types,
        SeoModuleSettings $settings,
        PlatformLocale $locales,
    ): RedirectResponse {
        $data = $request->validate([
            'locale' => ['nullable', 'string'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['nullable', 'array'],
            'modules.*.*' => ['nullable'],
            'modules.*.*.*' => ['nullable', 'string', 'max:2048'],
        ]);

        $availableLocales = $locales->languages();
        $activeLocale = (string) ($data['locale'] ?? $locales->current());

        if (! array_key_exists($activeLocale, $availableLocales)) {
            $activeLocale = $locales->defaultCode();
        }

        try {
            $activeModules = collect($types->all())->groupBy('module')->keys();

            foreach ($activeModules as $module) {
                $submittedModule = (array) (($data['modules'] ?? [])[$module] ?? []);
                $isNested = false;

                foreach (array_keys($availableLocales) as $locale) {
                    if (isset($submittedModule[$locale]) && is_array($submittedModule[$locale])) {
                        $isNested = true;
                        break;
                    }
                }

                foreach (array_keys($availableLocales) as $locale) {
                    if ($isNested) {
                        if (! array_key_exists($locale, $submittedModule)) {
                            continue;
                        }

                        $submitted = (array) $submittedModule[$locale];
                    } else {
                        // Backward compatibility with the earlier single-language form.
                        if ($locale !== $activeLocale) {
                            continue;
                        }

                        $submitted = $submittedModule;
                    }

                    $flags = [];

                    foreach (array_keys(SeoModuleSettings::FIELDS) as $field) {
                        $flags[$field] = trim((string) ($submitted[$field] ?? ''));
                    }

                    $settings->saveModule((string) $module, $flags, $locale);
                }
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['modules' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.plugins.seo.modules.index', ['locale' => $activeLocale])
            ->with('status', __('seo::ui.modules_saved'));
    }
}
