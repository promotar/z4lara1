<?php

namespace Modules\PageBuilder;

use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Rendering\PlatformContentRenderer;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ThemeCompositionService
{
    public function __construct(
        private readonly PlatformContentRenderer $renderer,
        private readonly SettingsRepository $settings,
        private readonly HookManager $hooks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageViewData(object $page, bool $isPreview = false): array
    {
        $values = $this->settings->values();
        $headers = $this->designs('header');
        $footers = $this->designs('footer');
        $pageHtml = $this->extendFrontendHtml(
            $this->renderer->renderHtml((string) ($page->html ?: $page->content)),
            $page,
        );

        return [
            'platformSettings' => $values,
            'dynamicHeaders' => $headers->map(fn (object $header): object => $this->section($header, $page)),
            'dynamicFooters' => $footers->map(fn (object $footer): object => $this->section($footer, $page)),
            'dynamicLayoutCss' => collect([
                $this->renderer->themeModeCss(),
                ...$headers->pluck('css')->map(fn ($css): string => trim((string) $css))->all(),
                ...$footers->pluck('css')->map(fn ($css): string => trim((string) $css))->all(),
            ])->filter()->implode("\n"),
            'pageCss' => trim((string) ($page->css ?? '')),
            'pageHtml' => $pageHtml,
            'siteTitle' => $values['general.site_title'] ?? config('app.name', 'Laravel'),
            'siteIcon' => $values['general.site_icon'] ?? null,
            'title' => $page->seo_title ?: $page->title,
            'description' => $page->meta_description ?? '',
            'isPreview' => $isPreview,
        ];
    }

    /**
     * @return array{dynamicHeaders:Collection, dynamicFooters:Collection, dynamicLayoutCss:string}
     */
    public function layoutViewData(?object $context = null): array
    {
        $headers = $this->designs('header');
        $footers = $this->designs('footer');
        $context ??= (object) [];

        return [
            'dynamicHeaders' => $headers->map(fn (object $header): object => $this->section($header, $context)),
            'dynamicFooters' => $footers->map(fn (object $footer): object => $this->section($footer, $context)),
            'dynamicLayoutCss' => collect([
                $this->renderer->themeModeCss(),
                ...$headers->pluck('css')->map(fn ($css): string => trim((string) $css))->all(),
                ...$footers->pluck('css')->map(fn ($css): string => trim((string) $css))->all(),
            ])->filter()->implode("\n"),
        ];
    }

    private function designs(string $placement): Collection
    {
        if (! Schema::hasTable('vvvebjs_layout_sections')) {
            return collect();
        }

        return DB::table('vvvebjs_layout_sections as layout')
            ->join('platform_pages as pages', 'pages.id', '=', 'layout.page_id')
            ->where('layout.placement', $placement)
            ->where('pages.status', 'published')
            ->orderBy('layout.sort_order')
            ->orderBy('layout.id')
            ->select('pages.*', 'layout.sort_order as layout_sort_order')
            ->get();
    }

    private function section(object $design, object $context): object
    {
        $section = clone $design;
        $section->rendered_html = $this->extendFrontendHtml(
            $this->renderer->renderHtml((string) ($design->html ?: $design->content)),
            $context,
        );

        return $section;
    }

    private function extendFrontendHtml(string $html, object $context): string
    {
        /*
         * PAGE BUILDER FRONTEND EXTENSION POINT
         * -------------------------------------
         * Builder plugins save inert, portable placeholders and resolve them through this filter.
         * This keeps plugin domain logic out of Page Builder. Because only active plugin hook files
         * are loaded, disabling/uninstalling a plugin also disables its renderer automatically.
         */
        $rendered = $this->hooks->applyFilters('plugin.page-builder.frontend.html', $html, $context);

        return is_string($rendered) ? $rendered : $html;
    }
}
