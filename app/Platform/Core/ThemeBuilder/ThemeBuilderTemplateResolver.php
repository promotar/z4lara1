<?php

namespace App\Platform\Core\ThemeBuilder;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ThemeBuilderTemplateResolver
{
    /**
     * @return Collection<int, object>
     */
    public function matchingTemplates(string $type, ?object $context = null): Collection
    {
        if (
            ! Schema::hasTable('platform_theme_builder_templates')
            || ! Schema::hasTable('platform_theme_builder_template_conditions')
        ) {
            return collect();
        }

        $templates = DB::table('platform_theme_builder_templates')
            ->where('template_type', $type)
            ->where('status', 'published')
            ->latest('updated_at')
            ->latest('id')
            ->get([
                'id',
                'template_type',
                'name',
                'slug',
                'html',
                'css',
                'page_builder_json',
                'metadata',
            ]);

        if ($templates->isEmpty()) {
            return collect();
        }

        $conditions = DB::table('platform_theme_builder_template_conditions')
            ->whereIn('template_id', $templates->pluck('id')->all())
            ->get()
            ->groupBy('template_id');

        return $templates
            ->filter(fn (object $template): bool => $this->templateMatches($conditions->get($template->id, collect()), $context))
            ->values();
    }

    public function firstMatchingTemplate(string $type, ?object $context = null): ?object
    {
        return $this->matchingTemplates($type, $context)->first();
    }

    /**
     * @return Collection<int, object>
     */
    public function matchingLayoutSections(string $type, ?object $context = null): Collection
    {
        return $this->matchingTemplates($type, $context)
            ->take(1)
            ->map(function (object $template) use ($type): object {
                $template->content_type = $type;
                $template->content = $template->html;

                return $template;
            });
    }

    /**
     * @param array<int, string> $types
     */
    public function matchingCss(array $types, ?object $context = null): string
    {
        return trim(collect($types)
            ->map(fn (string $type): ?object => $this->firstMatchingTemplate($type, $context))
            ->filter()
            ->pluck('css')
            ->filter(fn (?string $css): bool => is_string($css) && trim($css) !== '')
            ->implode("\n"));
    }

    /**
     * @param Collection<int, object> $conditions
     */
    private function templateMatches(Collection $conditions, ?object $context = null): bool
    {
        if ($conditions->isEmpty()) {
            return true;
        }

        $matchingExcludes = $conditions
            ->where('operator', 'exclude')
            ->filter(fn (object $condition): bool => $this->scopeMatches($condition, $context));

        if ($matchingExcludes->isNotEmpty()) {
            return false;
        }

        $includes = $conditions->where('operator', 'include');

        if ($includes->isEmpty()) {
            return true;
        }

        return $includes->contains(fn (object $condition): bool => $this->scopeMatches($condition, $context));
    }

    private function scopeMatches(object $condition, ?object $context = null): bool
    {
        $scope = (string) ($condition->scope ?? 'entire_site');
        $targets = $this->targets((string) ($condition->target_value ?? ''));

        return match ($scope) {
            'entire_site' => true,
            'front_page' => $this->isFrontPage($context),
            'all_pages' => $this->isPage($context),
            'specific_pages' => $this->matchesTargets($targets, $context),
            'all_posts' => $this->isPost($context),
            'specific_posts' => $this->matchesTargets($targets, $context),
            'post_categories' => $this->matchesCategoryTargets($targets, $context),
            'archives' => $this->isArchive(),
            'search_results' => $this->isSearch(),
            'not_found' => $this->isNotFound($context),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    private function targets(string $value): array
    {
        return collect(preg_split('/[\s,|]+/', $value) ?: [])
            ->map(fn (string $target): string => trim($target))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $targets
     */
    private function matchesTargets(array $targets, ?object $context): bool
    {
        if ($targets === [] || $context === null) {
            return false;
        }

        $values = collect([
            (string) ($context->id ?? ''),
            (string) ($context->slug ?? ''),
            '/pages/'.ltrim((string) ($context->slug ?? ''), '/'),
            (string) ($context->title ?? ''),
        ])->filter()->map(fn (string $value): string => strtolower($value));

        return collect($targets)
            ->map(fn (string $target): string => strtolower($target))
            ->contains(fn (string $target): bool => $values->contains($target));
    }

    /**
     * @param array<int, string> $targets
     */
    private function matchesCategoryTargets(array $targets, ?object $context): bool
    {
        if ($targets === [] || $context === null) {
            return false;
        }

        $values = collect([
            (string) ($context->category_id ?? ''),
            (string) ($context->category_slug ?? ''),
            (string) ($context->category ?? ''),
        ])->filter()->map(fn (string $value): string => strtolower($value));

        return collect($targets)
            ->map(fn (string $target): string => strtolower($target))
            ->contains(fn (string $target): bool => $values->contains($target));
    }

    private function isFrontPage(?object $context): bool
    {
        $path = trim(request()->path(), '/');

        return $path === '' || Route::currentRouteName() === 'front.home' || (bool) ($context?->is_front_page ?? false);
    }

    private function isPage(?object $context): bool
    {
        return (string) ($context->content_type ?? '') === 'page'
            || Route::currentRouteName() === 'pages.show';
    }

    private function isPost(?object $context): bool
    {
        $type = (string) ($context->content_type ?? $context->type ?? '');
        $route = (string) Route::currentRouteName();

        return in_array($type, ['post', 'blog_post'], true)
            || str_contains($route, 'blog')
            || str_contains($route, 'post');
    }

    private function isArchive(): bool
    {
        $route = (string) Route::currentRouteName();
        $path = request()->path();

        return str_contains($route, 'archive')
            || str_contains($route, 'category')
            || str_contains($route, 'tag')
            || str_contains($path, 'category')
            || str_contains($path, 'tag');
    }

    private function isSearch(): bool
    {
        $route = (string) Route::currentRouteName();

        return str_contains($route, 'search') || request()->has('q') || request()->has('search');
    }

    private function isNotFound(?object $context): bool
    {
        return (bool) ($context?->is_not_found ?? false);
    }
}
