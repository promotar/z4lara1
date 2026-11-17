<?php

namespace App\Platform\Core\Seo;

use Illuminate\Http\Request;
use Throwable;

/**
 * Runtime registry for optional SEO contributors.
 *
 * Core always owns this registry. Modules add contributors only while they are
 * active; the SEO Plugin may add a high-priority global contributor without
 * loading any inactive module code.
 */
final class SeoRegistry
{
    /** @var array<int, array{priority: int, contributor: SeoContributor}> */
    private array $contributors = [];

    /** @var array<int, array{priority: int, contributor: SitemapContributor}> */
    private array $sitemapContributors = [];

    public function add(SeoContributor $contributor, int $priority = 50): void
    {
        $this->contributors[] = ['priority' => $priority, 'contributor' => $contributor];
    }

    public function addSitemap(SitemapContributor $contributor, int $priority = 50): void
    {
        $this->sitemapContributors[] = ['priority' => $priority, 'contributor' => $contributor];
    }

    /**
     * @return array<int, SeoContributor>
     */
    public function contributorsFor(Request $request): array
    {
        $items = [];

        foreach ($this->contributors as $item) {
            try {
                if ($item['contributor']->supports($request)) {
                    $items[] = $item;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        usort($items, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_map(static fn (array $item): SeoContributor => $item['contributor'], $items);
    }

    /**
     * @return array<int, SitemapContributor>
     */
    public function sitemapContributors(): array
    {
        $items = $this->sitemapContributors;
        usort($items, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_map(static fn (array $item): SitemapContributor => $item['contributor'], $items);
    }

    /**
     * @return array<int, SeoContributor>
     */
    public function allContributors(): array
    {
        return array_map(static fn (array $item): SeoContributor => $item['contributor'], $this->contributors);
    }
}
