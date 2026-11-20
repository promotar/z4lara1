<?php

namespace Modules\Seo\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Resolves the content types that are actually available in this installation.
 *
 * The registry is intentionally SEO-owned: modules do not need to depend on the
 * SEO plugin, and inactive modules never appear in settings or audits.
 */
final class SeoContentTypeRegistry
{
    /** @var array<string, array{type:string,id:int}|null> */
    private array $recordUrlCache = [];

    private ?array $typesCache = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->typesCache !== null) {
            return $this->typesCache;
        }

        $active = $this->activeModuleSlugs();
        $types = [];

        foreach ($this->definitions() as $key => $definition) {
            if ($active !== null && ! in_array((string) $definition['module'], $active, true)) {
                continue;
            }

            if (! Schema::hasTable((string) $definition['table'])) {
                continue;
            }

            $types[$key] = $definition;
        }

        return $this->typesCache = $types;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return collect($this->all())
            ->mapWithKeys(static function (array $definition, string $key): array {
                $translated = __('seo::ui.content_type_'.$key);

                return [$key => is_string($translated) && $translated !== 'seo::ui.content_type_'.$key
                    ? $translated
                    : (string) $definition['label']];
            })
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    public function defaultSettings(): array
    {
        return collect($this->all())
            ->mapWithKeys(static fn (array $definition, string $key): array => [$key => true])
            ->all();
    }

    public function typeForUrl(string $url): ?string
    {
        $path = '/'.ltrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $path = $this->stripLocalePrefix($path);
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $available = $this->all();

        foreach ($this->definitions() as $key => $definition) {
            if (! array_key_exists($key, $available)) {
                continue;
            }

            $pattern = (string) ($definition['url_pattern'] ?? '');

            if ($pattern !== '' && preg_match($pattern, $path) === 1) {
                return $key;
            }
        }

        return null;
    }

    public function records(string $key): ?Builder
    {
        $definitions = $this->all();

        if (! array_key_exists($key, $definitions)) {
            return null;
        }

        return match ($key) {
            'blog_post' => DB::table('blog_posts')
                ->select([
                    'id', 'title', 'slug', 'seo_title', 'meta_title', 'seo_description',
                    'meta_description', 'excerpt', 'robots_index', 'robots_follow', 'updated_at',
                ])
                ->whereNull('deleted_at')
                ->where('status', 'published')
                ->where('visibility', 'public'),
            'page' => DB::table('platform_pages')
                ->select(['id', 'title', 'slug', 'seo_title', 'meta_description', 'content', 'updated_at'])
                ->where('content_type', 'page')
                ->where('status', 'published'),
            'lms_course' => DB::table('lms_courses')
                ->select([
                    'id', 'title', 'slug', 'meta_title', 'meta_description', 'short_description',
                    'description', 'robots_index', 'robots_follow', 'updated_at',
                ])
                ->whereNull('deleted_at')
                ->where('status', 'published')
                ->where(function (Builder $query): void {
                    $query->whereNull('visibility')->orWhere('visibility', 'public');
                }),
            'store_product' => DB::table('store_products as product')
                ->join('store_product_translations as translation', function ($join): void {
                    $join->on('translation.product_id', '=', 'product.id')
                        ->on('translation.locale', '=', 'product.default_locale');
                })
                ->select([
                    'product.id',
                    'translation.name as title',
                    'translation.slug',
                    'translation.meta_title',
                    'translation.meta_description',
                    'translation.short_description',
                    'translation.description',
                    'product.updated_at',
                ])
                ->where('product.status', 'published')
                ->where('product.visibility', 'public')
                ->where('product.type_code', '!=', 'artwork')
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('store_external_sellables as external')
                        ->whereColumn('external.product_id', 'product.id');
                }),
            'exhibition_artwork' => DB::table('exhibition_artworks as artwork')
                ->join('exhibition_artwork_publications as publication', function ($join): void {
                    $join->on('publication.artwork_id', '=', 'artwork.id')
                        ->where('publication.state', 'published');
                })
                ->select([
                    'artwork.id',
                    'artwork.title',
                    'artwork.slug',
                    'artwork.public_id',
                    'artwork.seo_title',
                    'artwork.meta_description',
                    'artwork.short_description',
                    'artwork.detailed_description',
                    'artwork.updated_at',
                ])
                ->where('artwork.state', 'published')
                ->whereNull('artwork.deleted_at'),
            default => null,
        };
    }

    public function indexableCount(): int
    {
        $count = 0;

        foreach ($this->all() as $key => $definition) {
            try {
                $query = $this->records($key);

                if ($query === null) {
                    continue;
                }

                if (isset($definition['robots_index']) && Schema::hasColumn((string) $definition['table'], 'robots_index')) {
                    $query->where(function (Builder $robots): void {
                        $robots->whereNull('robots_index')->orWhere('robots_index', true);
                    });
                }

                $count += (int) $query->count();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $count;
    }

    public function publicUrl(string $key, object $record): ?string
    {
        $definition = $this->all()[$key] ?? null;

        if ($definition === null) {
            return null;
        }

        $slug = trim((string) ($record->slug ?? ''));
        $publicId = trim((string) ($record->public_id ?? ''));

        if ($key === 'exhibition_artwork') {
            $slug = $slug !== '' ? $slug : $publicId;
        }

        if ($slug === '') {
            return null;
        }

        $route = (string) ($definition['public_route'] ?? '');
        $parameter = match ($key) {
            'lms_course' => 'course',
            'exhibition_artwork' => 'artwork',
            default => 'slug',
        };

        try {
            if ($route !== '' && Route::has($route)) {
                return route($route, [$parameter => $slug]);
            }
        } catch (Throwable) {
            // Fall through to the path form when a route needs a different parameter.
        }

        return url('/'.trim((string) ($definition['public_prefix'] ?? ''), '/').'/'.$slug);
    }

    public function adminUrl(string $key, object $record): ?string
    {
        $definition = $this->all()[$key] ?? null;

        if ($definition === null) {
            return null;
        }

        $route = trim((string) ($definition['admin_route'] ?? ''));

        try {
            if ($route !== '' && Route::has($route)) {
                return route($route);
            }
        } catch (Throwable) {
            // Use the configured path fallback below.
        }

        return isset($definition['admin_path']) ? url((string) $definition['admin_path']) : null;
    }

    public function customTitle(string $key, object $record): string
    {
        return $this->firstValue($record, (array) ($this->definitions()[$key]['custom_title'] ?? []));
    }

    public function customDescription(string $key, object $record): string
    {
        return $this->firstValue($record, (array) ($this->definitions()[$key]['custom_description'] ?? []));
    }

    /**
     * Resolve a public URL to a content record for sitemap policy checks.
     *
     * @return array{type:string,id:int}|null
     */
    public function recordForUrl(string $url): ?array
    {
        if (array_key_exists($url, $this->recordUrlCache)) {
            return $this->recordUrlCache[$url];
        }

        $type = $this->typeForUrl($url);

        if ($type === null) {
            return $this->recordUrlCache[$url] = null;
        }

        $path = '/'.ltrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $path = $this->stripLocalePrefix($path);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        try {
            $id = match ($type) {
                'blog_post' => DB::table('blog_posts')->where('slug', $segments[1] ?? '')->value('id'),
                'lms_course' => DB::table('lms_courses')->where('slug', $segments[1] ?? '')->value('id'),
                'store_product' => DB::table('store_product_translations')
                    ->where('slug', $segments[2] ?? '')
                    ->value('product_id'),
                'exhibition_artwork' => DB::table('exhibition_artworks')
                    ->where(function (Builder $query) use ($segments): void {
                        $query->where('slug', $segments[2] ?? '')->orWhere('public_id', $segments[2] ?? '');
                    })
                    ->value('id'),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $this->recordUrlCache[$url] = ($id ? ['type' => $type, 'id' => (int) $id] : null);
    }

    public function robotsIndex(string $key, object $record): bool
    {
        $columns = (array) ($this->definitions()[$key]['robots_index'] ?? []);

        if ($columns === []) {
            return true;
        }

        $value = $this->firstValue($record, $columns);

        return $value === '' || ! in_array($value, ['0', 'false', 'off'], true);
    }

    /**
     * @return array<int, array{loc: string}>
     */
    public function imagesForUrl(string $url, bool $includeFeatured = true): array
    {
        $path = '/'.ltrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $path = $this->stripLocalePrefix($path);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $images = [];

        try {
            if (preg_match('#^/blog/[^/]+$#', $path) === 1 && count($segments) === 2) {
                $post = DB::table('blog_posts as post')
                    ->leftJoin('blog_media as media', 'media.id', '=', 'post.featured_image_id')
                    ->where('post.slug', $segments[1])
                    ->select(['post.featured_image', 'media.url as media_url'])
                    ->first();

                if ($includeFeatured && $post !== null) {
                    $images[] = $this->absoluteImage($post->featured_image ?: $post->media_url);
                }
            } elseif (preg_match('#^/courses/[^/]+$#', $path) === 1 && count($segments) === 2) {
                $course = DB::table('lms_courses')
                    ->where('slug', $segments[1])
                    ->select(['featured_image_path', 'featured_image_meta'])
                    ->first();

                if ($includeFeatured && $course !== null) {
                    $meta = json_decode((string) $course->featured_image_meta, true);
                    $images[] = $this->absoluteImage(is_array($meta) ? ($meta['url'] ?? null) : null);
                    $images[] = $this->absoluteImage($course->featured_image_path);
                }
            } elseif (preg_match('#^/store/products/[^/]+$#', $path) === 1 && count($segments) === 3) {
                $media = DB::table('store_products as product')
                    ->join('store_product_translations as translation', function ($join): void {
                        $join->on('translation.product_id', '=', 'product.id')
                            ->on('translation.locale', '=', 'product.default_locale');
                    })
                    ->join('store_product_media as product_media', 'product_media.product_id', '=', 'product.id')
                    ->join('store_media_assets as asset', 'asset.id', '=', 'product_media.media_asset_id')
                    ->where('translation.slug', $segments[2])
                    ->where('product_media.visibility', 'public')
                    ->where('asset.status', 'ready')
                    ->when(! $includeFeatured, fn (Builder $query) => $query->where('product_media.role', '!=', 'cover'))
                    ->orderByDesc('product_media.is_primary')
                    ->orderBy('product_media.position')
                    ->limit(10)
                    ->pluck('asset.path')
                    ->all();

                foreach ($media as $assetPath) {
                    $images[] = $this->absoluteImage('/storage/'.ltrim((string) $assetPath, '/'));
                }
            } elseif (preg_match('#^/exhibitions/artworks/[^/]+$#', $path) === 1 && count($segments) === 3) {
                $artwork = DB::table('exhibition_artworks')
                    ->where(function (Builder $query) use ($segments): void {
                        $query->where('slug', $segments[2])->orWhere('public_id', $segments[2]);
                    })
                    ->first(['id', 'public_id']);

                if ($artwork !== null) {
                    $media = DB::table('exhibition_artwork_media')
                        ->where('artwork_id', $artwork->id)
                        ->where('state', 'ready')
                        ->whereNull('deleted_at')
                        ->when(! $includeFeatured, fn (Builder $query) => $query->where('role', '!=', 'primary'))
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->limit(10)
                        ->get(['public_id']);

                    foreach ($media as $item) {
                        if (Route::has('exhibitions.artworks.media')) {
                            $images[] = route('exhibitions.artworks.media', [
                                'artwork' => $artwork->public_id,
                                'media' => $item->public_id,
                            ]);
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return collect($images)
            ->filter(static fn (mixed $image): bool => is_string($image) && trim($image) !== '')
            ->unique()
            ->map(static fn (string $image): array => ['loc' => $image])
            ->values()
            ->all();
    }

    private function stripLocalePrefix(string $path): string
    {
        return preg_replace('#^/[a-z]{2}(?:-[A-Z]{2})?(?=/)#', '', $path) ?: $path;
    }

    private function absoluteImage(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : url($value);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function firstValue(object $record, array $columns): string
    {
        foreach ($columns as $column) {
            $segments = explode('.', $column);
            $value = $record;

            foreach ($segments as $segment) {
                if (is_object($value)) {
                    $value = $value->{$segment} ?? null;
                } elseif (is_array($value)) {
                    $value = $value[$segment] ?? null;
                } else {
                    $value = null;
                }
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @return array<int, string>|null
     */
    private function activeModuleSlugs(): ?array
    {
        try {
            if (! Schema::hasTable('plugins')) {
                return null;
            }

            return DB::table('plugins')
                ->where('status', 'active')
                ->pluck('slug')
                ->map(static fn (mixed $slug): string => (string) $slug)
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'blog_post' => [
                'label' => 'Posts',
                'module' => 'blog',
                'table' => 'blog_posts',
                'custom_title' => ['seo_title', 'meta_title'],
                'custom_description' => ['seo_description', 'meta_description'],
                'robots_index' => ['robots_index'],
                'public_route' => 'blog.show',
                'public_prefix' => 'blog',
                'admin_route' => 'admin.plugins.blog.posts.index',
                'url_pattern' => '#^/blog/(?!(?:category|tag|search|categories)(?:/|$))[^/]+$#',
            ],
            'blog_category' => [
                'label' => 'Categories',
                'module' => 'blog',
                'table' => 'blog_categories',
                'custom_title' => ['seo_title', 'meta_title'],
                'custom_description' => ['seo_description', 'meta_description'],
                'public_route' => 'blog.category',
                'public_prefix' => 'blog/category',
                'admin_route' => 'admin.plugins.blog.categories.index',
                'url_pattern' => '#^/blog/category/[^/]+$#',
            ],
            'blog_tag' => [
                'label' => 'Tags',
                'module' => 'blog',
                'table' => 'blog_tags',
                'custom_title' => ['seo_title', 'meta_title'],
                'custom_description' => ['seo_description', 'meta_description'],
                'public_route' => 'blog.tag',
                'public_prefix' => 'blog/tag',
                'admin_route' => 'admin.plugins.blog.tags.index',
                'url_pattern' => '#^/blog/tag/[^/]+$#',
            ],
            'store_category' => [
                'label' => 'Product categories',
                'module' => 'store',
                'table' => 'store_categories',
                'public_route' => 'store.categories.show',
                'public_prefix' => 'store/categories',
                'admin_route' => 'admin.plugins.store.categories.index',
                'url_pattern' => '#^/store/categories/[^/]+$#',
            ],
            'store_brand' => [
                'label' => 'Brands',
                'module' => 'store',
                'table' => 'store_brands',
                'public_route' => 'store.brands.show',
                'public_prefix' => 'store/brands',
                'admin_route' => 'admin.plugins.store.brands.index',
                'url_pattern' => '#^/store/brands/[^/]+$#',
            ],
            'page' => [
                'label' => 'Pages',
                'module' => 'page-builder',
                'table' => 'platform_pages',
                'custom_title' => ['seo_title'],
                'custom_description' => ['meta_description'],
                'public_route' => 'pages.show',
                'public_prefix' => 'pages',
                'admin_route' => 'admin.pages.index',
                'url_pattern' => '#^/pages/[^/]+$#',
            ],
            'lms_course' => [
                'label' => 'Courses',
                'module' => 'lms',
                'table' => 'lms_courses',
                'custom_title' => ['meta_title'],
                'custom_description' => ['meta_description'],
                'robots_index' => ['robots_index'],
                'public_route' => 'lms.front.courses.show',
                'public_prefix' => 'courses',
                'admin_route' => 'admin.plugins.lms.courses.index',
                'url_pattern' => '#^/courses/[^/]+$#',
            ],
            'store_product' => [
                'label' => 'Products',
                'module' => 'store',
                'table' => 'store_products',
                'custom_title' => ['meta_title'],
                'custom_description' => ['meta_description'],
                'public_route' => 'store.products.show',
                'public_prefix' => 'store/products',
                'admin_route' => 'admin.plugins.store.products.index',
                'url_pattern' => '#^/store/products/[^/]+$#',
            ],
            'exhibition_artwork' => [
                'label' => 'Artwork',
                'module' => 'exhibitions',
                'table' => 'exhibition_artworks',
                'custom_title' => ['seo_title'],
                'custom_description' => ['meta_description'],
                'public_route' => 'exhibitions.artworks.show',
                'public_prefix' => 'exhibitions/artworks',
                'admin_route' => 'admin.plugins.exhibitions.workspace',
                'admin_path' => '/admin/plugins/exhibitions/workspace/artworks',
                'url_pattern' => '#^/exhibitions/artworks/[^/]+$#',
            ],
        ];
    }
}
