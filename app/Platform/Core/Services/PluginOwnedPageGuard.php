<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PluginOwnedPageGuard
{
    /** @var array<string, array<int, string>>|null */
    private ?array $ownersByPage = null;

    /** @var array<int, array{slug: string, manifests: array<int, array<string, mixed>>}>|null */
    private ?array $pluginDefinitions = null;

    /** @var array<string, bool> */
    private array $publishedPageAvailability = [];

    public function __construct(
        private readonly PluginRuntimeGate $gate,
        private readonly ?PluginFilesystem $filesystem = null,
    ) {}

    public function isRouteAvailable(string $routeName): bool
    {
        $routeName = trim($routeName);

        return $routeName !== ''
            && Route::has($routeName)
            && $this->isNavigationAvailable($routeName, null);
    }
    public function isAvailable(string $pageSlug): bool
    {
        foreach ($this->ownersFor($pageSlug) as $owner) {
            if (! $this->gate->allows($owner)) {
                return false;
            }
        }

        return true;
    }

    public function isPageAvailable(object $page): bool
    {
        $slug = trim((string) ($page->slug ?? ''));

        return $this->isAvailable($slug) && $this->isContentAvailable($page);
    }

    public function isContentAvailable(object|string $content): bool
    {
        $source = is_string($content)
            ? $content
            : collect(['html', 'content', 'project_data'])
                ->map(static fn (string $field): string => (string) ($content->{$field} ?? ''))
                ->filter()
                ->implode("\n");

        if ($source === '') {
            return true;
        }

        foreach (['data-platform-blog-archive', 'data-platform-news-ticker', 'data-platform-latest-mega'] as $marker) {
            if (str_contains($source, $marker) && ! $this->gate->allows('blog')) {
                return false;
            }
        }



        return true;
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    public function isNavigationAvailable(?string $routeName, ?string $url, array $routeParams = []): bool
    {
        $routeName = trim((string) $routeName);
        $path = $this->navigationPath($url);

        if ($routeName === 'pages.show' && isset($routeParams['slug']) && ! $this->publishedPageIsAvailable((string) $routeParams['slug'])) {
            return false;
        }

        if ($path !== null && preg_match('~^/pages/([^/]+)$~', $path, $matches)) {
            if (! $this->publishedPageIsAvailable(rawurldecode($matches[1]))) {
                return false;
            }
        }

        foreach ($this->definitions() as $definition) {
            foreach ($definition['manifests'] as $manifest) {
                foreach ($this->normalizePaths(data_get($manifest, 'frontend.owned_paths', [])) as $ownedPath) {
                    if ($path !== null && $this->pathMatches($path, $ownedPath)) {
                        return $this->gate->allows($definition['slug']);
                    }
                }

                foreach (['web', 'admin', 'api'] as $type) {                    $namePrefix = $this->routeNamePrefix($manifest, $definition['slug'], $type);
                    $urlPrefix = $this->routeUrlPrefix($manifest, $definition['slug'], $type);

                    if ($routeName !== '' && $namePrefix !== '' && str_starts_with($routeName, $namePrefix)) {
                        return $this->gate->allows($definition['slug']);
                    }

                    if ($path !== null && $urlPrefix !== '' && ($path === $urlPrefix || str_starts_with($path, $urlPrefix.'/'))) {
                        return $this->gate->allows($definition['slug']);
                    }
                }
            }
        }

        return true;
    }

    private function publishedPageIsAvailable(string $pageSlug): bool
    {
        $pageSlug = trim($pageSlug);

        if (array_key_exists($pageSlug, $this->publishedPageAvailability)) {
            return $this->publishedPageAvailability[$pageSlug];
        }

        if (! $this->isAvailable($pageSlug) || ! Schema::hasTable('platform_pages')) {
            return $this->publishedPageAvailability[$pageSlug] = $this->isAvailable($pageSlug);
        }

        try {
            $page = DB::table('platform_pages')
                ->where('slug', $pageSlug)
                ->where('content_type', 'page')
                ->where('status', 'published')
                ->first();
        } catch (Throwable) {
            return $this->publishedPageAvailability[$pageSlug] = true;
        }

        return $this->publishedPageAvailability[$pageSlug] = $page === null || $this->isContentAvailable($page);
    }
    /**
     * @return array<int, string>
     */
    public function ownersFor(string $pageSlug): array
    {
        $pageSlug = trim($pageSlug);

        if ($pageSlug === '') {
            return [];
        }

        return $this->ownership()[$pageSlug] ?? [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function ownership(): array
    {
        if ($this->ownersByPage !== null) {
            return $this->ownersByPage;
        }

        $this->ownersByPage = [];

        foreach ($this->definitions() as $definition) {
            foreach ($definition['manifests'] as $manifest) {
                foreach ($this->normalizePages(data_get($manifest, 'frontend.owned_pages', [])) as $pageSlug) {
                    $this->ownersByPage[$pageSlug] ??= [];
                    $this->ownersByPage[$pageSlug][] = $definition['slug'];
                    $this->ownersByPage[$pageSlug] = array_values(array_unique($this->ownersByPage[$pageSlug]));
                }
            }
        }

        return $this->ownersByPage;
    }

    /**
     * @return array<int, array{slug: string, manifests: array<int, array<string, mixed>>}>
     */
    private function definitions(): array
    {
        if ($this->pluginDefinitions !== null) {
            return $this->pluginDefinitions;
        }

        $this->pluginDefinitions = [];

        if (! Schema::hasTable('plugins')) {
            return $this->pluginDefinitions;
        }

        try {
            $plugins = Plugin::query()->get(['slug', 'path', 'manifest']);
        } catch (Throwable) {
            return $this->pluginDefinitions;
        }

        foreach ($plugins as $plugin) {
            $manifests = [];
            $databaseManifest = is_array($plugin->manifest) ? $plugin->manifest : [];
            $fileManifest = $this->manifestFromFile($plugin);

            if ($databaseManifest !== []) {
                $manifests[] = $databaseManifest;
            }
            if ($fileManifest !== [] && $fileManifest !== $databaseManifest) {
                $manifests[] = $fileManifest;
            }

            $this->pluginDefinitions[] = [
                'slug' => $plugin->slug,
                'manifests' => $manifests,
            ];
        }

        return $this->pluginDefinitions;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePaths(mixed $paths): array
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }

        if (! is_array($paths)) {
            return [];
        }

        return collect($paths)
            ->map(static function (mixed $path): string {
                $path = trim((string) $path);

                return $path === '' ? '' : '/'.ltrim($path, '/');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function pathMatches(string $path, string $ownedPath): bool
    {
        if (str_ends_with($ownedPath, '/*')) {
            $prefix = rtrim(substr($ownedPath, 0, -2), '/');

            return $path === $prefix || str_starts_with($path, $prefix.'/');
        }

        return $path === rtrim($ownedPath, '/') || ($path === '/' && $ownedPath === '/');
    }
    /**
     * @return array<int, string>
     */
    private function normalizePages(mixed $pages): array
    {
        if (is_string($pages)) {
            $pages = [$pages];
        }

        if (! is_array($pages)) {
            return [];
        }

        return collect($pages)
            ->map(static fn (mixed $page): string => is_array($page)
                ? trim((string) ($page['slug'] ?? ''))
                : trim((string) $page))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function routeNamePrefix(array $manifest, string $slug, string $type): string
    {
        $configured = data_get($manifest, "routes.{$type}.name");

        if (is_string($configured)) {
            $configured = trim($configured, '.');

            return $configured === '' ? '' : $configured.'.';
        }

        return match ($type) {
            'admin' => "admin.plugins.{$slug}.",
            'api' => "api.plugins.{$slug}.",
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function routeUrlPrefix(array $manifest, string $slug, string $type): string
    {
        $configured = data_get($manifest, "routes.{$type}.prefix");

        if (is_string($configured)) {
            $configured = trim($configured, '/');

            return $configured === '' ? '' : '/'.$configured;
        }

        return match ($type) {
            'admin' => '/admin/plugins/'.$slug,
            'api' => '/plugins/'.$slug,
            default => '',
        };
    }

    private function navigationPath(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return '/'.trim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestFromFile(Plugin $plugin): array
    {
        return ($this->filesystem ?? app(PluginFilesystem::class))->manifest($plugin);
    }
}
