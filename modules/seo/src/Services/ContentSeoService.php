<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\LocaleUrlStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoMeta;
use Throwable;

/**
 * Stores only custom SEO overrides for content records.
 *
 * Overrides are locale-aware. The default content locale keeps using the
 * module's original SEO columns, while translated locales use their existing
 * translation SEO columns where available and seo_meta otherwise.
 */
final class ContentSeoService
{
    private const TYPES = [
        'blog_post' => ['module' => 'blog', 'table' => 'blog_posts'],
        'lms_course' => ['module' => 'lms', 'table' => 'lms_courses'],
        'store_product' => ['module' => 'store', 'table' => 'store_products'],
        'exhibition_artwork' => ['module' => 'exhibitions', 'table' => 'exhibition_artworks'],
    ];

    /** @var array<string, SeoMeta|null> */
    private array $rowCache = [];

    /** @var array<string, array{title:string,description:string,focus_keyword:string,robots:string,canonical:string}> */
    private array $legacyCache = [];

    /** @var array<string, bool> */
    private array $noindexCache = [];

    public function __construct(private readonly SeoSiteResolver $sites)
    {
    }

    /**
     * @return array{title:string,description:string,focus_keyword:string,robots:string,canonical:string}
     */
    public function custom(string $type, ?int $id, ?string $locale = null): array
    {
        $empty = ['title' => '', 'description' => '', 'focus_keyword' => '', 'robots' => '', 'canonical' => ''];

        if ($id === null || $id <= 0) {
            return $empty;
        }

        $locale = $this->locale($locale);
        $legacy = $this->legacyValues($type, $id, $locale);
        $row = $this->row($type, $id, $locale);

        return [
            'title' => $this->firstCustom($row?->title, $legacy['title']),
            'description' => $this->firstCustom($row?->description, $legacy['description']),
            'focus_keyword' => $this->firstCustom($row?->focus_keyword, $legacy['focus_keyword']),
            'robots' => $this->normalizeRobots($row?->robots) ?: $legacy['robots'],
            'canonical' => $this->firstCustom($row?->canonical, $legacy['canonical']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function build(string $type, ?int $id, array $content, SeoDefaults $defaults, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $siteId = $this->sites->id();
        $settings = $defaults->forSite($siteId);
        $custom = $this->custom($type, $id, $locale);

        $siteName = trim((string) ($settings['site_name'] ?? ''));
        $contentTitle = trim((string) ($content['title'] ?? ''));
        $contentDescription = trim((string) ($content['description'] ?? ''));
        $contentUrl = trim((string) ($content['url'] ?? ''));

        if ($contentUrl !== '' && $locale !== PlatformLocale::DEFAULT) {
            try {
                $contentUrl = app(LocaleUrlStrategy::class)->withLocale($contentUrl, $locale);
            } catch (Throwable) {
                // Keep the supplied URL when a locale strategy is unavailable.
            }
        }

        $contentImage = trim((string) ($content['image'] ?? ''));
        $defaultDescription = trim((string) ($settings['default_description'] ?? ''));
        $defaultImage = trim((string) ($settings['social_image'] ?? $settings['default_og_image'] ?? ''));
        $titleTemplate = trim((string) ($settings['title_template'] ?? '{title} | {site_name}'));

        $effectiveTitle = $custom['title'];
        if ($effectiveTitle === '') {
            $effectiveTitle = $this->titleFromTemplate($contentTitle, $siteName, $titleTemplate);
        }
        $effectiveDescription = $custom['description'] ?: $contentDescription ?: $defaultDescription;
        $effectiveCanonical = $custom['canonical'] ?: $contentUrl;
        $effectiveImage = $contentImage ?: $defaultImage;
        $effectiveRobots = $custom['robots'] ?: (string) ($settings['robots_behavior'] ?? 'index_follow');

        return [
            'contentType' => $type,
            'contentId' => $id,
            'locale' => $locale,
            'custom' => $custom,
            'preview' => [
                'contentTitle' => $contentTitle,
                'contentDescription' => $contentDescription,
                'contentUrl' => $contentUrl,
                'contentImage' => $contentImage,
                'siteName' => $siteName,
                'titleTemplate' => $titleTemplate,
                'defaultDescription' => $defaultDescription,
                'defaultImage' => $defaultImage,
                'robotsDefault' => $effectiveRobots,
                'effectiveTitle' => $effectiveTitle,
                'effectiveDescription' => $effectiveDescription,
                'effectiveCanonical' => $effectiveCanonical,
                'effectiveImage' => $effectiveImage,
            ],
        ];
    }

    public function save(Request $request, string $type, ?int $id, ?string $locale = null): void
    {
        if ($id === null || $id <= 0 || ! array_key_exists($type, self::TYPES)) {
            return;
        }

        $locale = $this->locale($locale ?? $request->input('locale'));
        $values = [
            'title' => $this->trimOrNull($request->input('seo_title')),
            'description' => $this->trimOrNull($request->input('seo_description')),
            'focus_keyword' => $this->trimOrNull($request->input('focus_keyword')),
            'canonical' => $this->trimOrNull($request->input('canonical_url')),
            'robots' => $this->normalizeRobots($request->input('seo_robots')),
        ];

        try {
            $this->saveRow($type, $id, $locale, $values);
            $this->forget($type, $id);
            $this->syncLegacy($type, $id, $values, $locale);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{title:string,description:string,focus_keyword:string,robots:string,canonical:string}
     */
    public function override(string $type, ?int $id, ?string $locale = null): array
    {
        $empty = ['title' => '', 'description' => '', 'focus_keyword' => '', 'robots' => '', 'canonical' => ''];

        if ($id === null || $id <= 0) {
            return $empty;
        }

        $row = $this->row($type, $id, $this->locale($locale));

        if ($row === null) {
            return $empty;
        }

        return [
            'title' => trim((string) ($row->title ?? '')),
            'description' => trim((string) ($row->description ?? '')),
            'focus_keyword' => trim((string) ($row->focus_keyword ?? '')),
            'robots' => $this->normalizeRobots($row->robots),
            'canonical' => trim((string) ($row->canonical ?? '')),
        ];
    }

    public function isNoindex(string $type, ?int $id, ?string $locale = null): bool
    {
        if ($id === null) {
            return false;
        }

        $locale = $this->locale($locale);
        $cacheKey = $type.':'.$id.':'.$locale;

        if (array_key_exists($cacheKey, $this->noindexCache)) {
            return $this->noindexCache[$cacheKey];
        }

        $row = $this->row($type, $id, $locale);

        if ($this->normalizeRobots($row?->robots) === 'noindex') {
            return $this->noindexCache[$cacheKey] = true;
        }

        $legacy = $this->legacyValues($type, $id, $locale);

        return $this->noindexCache[$cacheKey] = $legacy['robots'] === 'noindex';
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function titleFromTemplate(string $contentTitle, string $siteName, string $template): string
    {
        $title = $contentTitle !== '' ? $contentTitle : $siteName;

        if ($title === '' || $template === '') {
            return $title;
        }

        if ($siteName !== '' && str_contains($title, $siteName)) {
            return $title;
        }

        return trim(str_replace(['{title}', '{site_name}'], [$title, $siteName], $template));
    }

    private function row(string $type, int $id, string $locale): ?SeoMeta
    {
        if (! array_key_exists($type, self::TYPES)) {
            return null;
        }

        $key = $type.':'.$id.':'.$locale;

        if (! array_key_exists($key, $this->rowCache)) {
            $row = $this->queryRow($type, $id, $locale);

            // Older integration data used an empty locale for the default
            // locale. Keep it as a fallback until the next save normalizes it.
            if ($row === null && $this->isDefaultLocale($locale)) {
                $row = $this->queryRow($type, $id, '');
            }

            $this->rowCache[$key] = $row;
        }

        return $this->rowCache[$key];
    }

    private function queryRow(string $type, int $id, string $locale): ?SeoMeta
    {
        return SeoMeta::query()
            ->where('site_id', $this->sites->id())
            ->where('module_slug', self::TYPES[$type]['module'])
            ->where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function saveRow(string $type, int $id, string $locale, array $values): void
    {
        $rows = SeoMeta::query()
            ->where('site_id', $this->sites->id())
            ->where('module_slug', self::TYPES[$type]['module'])
            ->where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->whereIn('locale', $this->isDefaultLocale($locale) ? [$locale, ''] : [$locale])
            ->get();

        if ($this->isEmpty($values)) {
            $rows->each->delete();

            return;
        }

        $row = $rows->firstWhere('locale', $locale) ?? $rows->first();

        if ($row === null) {
            $row = new SeoMeta([
                'site_id' => $this->sites->id(),
                'module_slug' => self::TYPES[$type]['module'],
                'seoable_type' => $type,
                'seoable_id' => $id,
                'locale' => $locale,
            ]);
        } else {
            // Move the legacy empty-locale row to the explicit default locale.
            $row->locale = $locale;
        }

        $row->fill($values)->save();

        // Remove a duplicate legacy row when normalizing the default locale.
        $rows->where('id', '!=', $row->id)->each->delete();
    }

    /**
     * @return array{title:string,description:string,focus_keyword:string,robots:string,canonical:string}
     */
    private function legacyValues(string $type, int $id, string $locale): array
    {
        $cacheKey = $type.':'.$id.':'.$locale;

        if (array_key_exists($cacheKey, $this->legacyCache)) {
            return $this->legacyCache[$cacheKey];
        }

        $empty = ['title' => '', 'description' => '', 'focus_keyword' => '', 'robots' => '', 'canonical' => ''];
        $isDefault = $this->isDefaultLocale($locale);

        try {
            $row = match ($type) {
                'blog_post' => $isDefault
                    ? DB::table('blog_posts')->where('id', $id)->first()
                    : DB::table('blog_post_translations')->where('post_id', $id)->where('locale', $locale)->first(),
                'lms_course' => $isDefault ? DB::table('lms_courses')->where('id', $id)->first() : null,
                'store_product' => DB::table('store_product_translations')
                    ->where('product_id', $id)
                    ->where('locale', $locale)
                    ->first(),
                'exhibition_artwork' => $isDefault
                    ? DB::table('exhibition_artworks')->where('id', $id)->first()
                    : DB::table('exhibition_artwork_translations')->where('artwork_id', $id)->where('locale', $locale)->first(),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return $empty;
        }

        if ($row === null) {
            return $this->legacyCache[$cacheKey] = $empty;
        }

        $robots = '';
        if ($isDefault
            && in_array($type, ['blog_post', 'lms_course'], true)
            && isset($row->robots_index)
            && (int) $row->robots_index === 0
        ) {
            $robots = 'noindex';
        }

        return $this->legacyCache[$cacheKey] = match ($type) {
            'blog_post' => [
                'title' => (string) ($row->seo_title ?? ''),
                'description' => (string) ($row->seo_description ?? ''),
                'focus_keyword' => (string) ($row->focus_keyword ?? ''),
                'robots' => $robots,
                'canonical' => (string) ($row->canonical_url ?? ''),
            ],
            'lms_course' => [
                'title' => (string) ($row->meta_title ?? ''),
                'description' => (string) ($row->meta_description ?? ''),
                'focus_keyword' => '',
                'robots' => $robots,
                'canonical' => (string) ($row->canonical_url ?? ''),
            ],
            'store_product' => [
                'title' => (string) ($row->meta_title ?? ''),
                'description' => (string) ($row->meta_description ?? ''),
                'focus_keyword' => '',
                'robots' => '',
                'canonical' => '',
            ],
            'exhibition_artwork' => [
                'title' => (string) ($row->seo_title ?? ''),
                'description' => (string) ($row->meta_description ?? ''),
                'focus_keyword' => '',
                'robots' => '',
                'canonical' => '',
            ],
            default => $empty,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function syncLegacy(string $type, int $id, array $values, string $locale): void
    {
        try {
            $isDefault = $this->isDefaultLocale($locale);
            $robots = $values['robots'] === 'index' ? 1 : ($values['robots'] === 'noindex' ? 0 : null);

            if ($isDefault) {
                match ($type) {
                    'blog_post' => DB::table('blog_posts')->where('id', $id)->update([
                        'seo_title' => $values['title'],
                        'seo_description' => $values['description'],
                        'focus_keyword' => $values['focus_keyword'],
                        'canonical_url' => $values['canonical'],
                        'robots_index' => $robots,
                        'robots_follow' => null,
                    ]),
                    'lms_course' => DB::table('lms_courses')->where('id', $id)->update([
                        'meta_title' => $values['title'],
                        'meta_description' => $values['description'],
                        'canonical_url' => $values['canonical'],
                        'robots_index' => $robots,
                        'robots_follow' => null,
                    ]),
                    'store_product' => DB::table('store_product_translations')
                        ->where('product_id', $id)
                        ->where('locale', $locale)
                        ->update([
                            'meta_title' => $values['title'],
                            'meta_description' => $values['description'],
                        ]),
                    'exhibition_artwork' => DB::table('exhibition_artworks')->where('id', $id)->update([
                        'seo_title' => $this->limited($values['title'], 70),
                        'meta_description' => $this->limited($values['description'], 160),
                    ]),
                    default => null,
                };

                return;
            }

            match ($type) {
                'blog_post' => DB::table('blog_post_translations')
                    ->where('post_id', $id)
                    ->where('locale', $locale)
                    ->update([
                        'seo_title' => $values['title'],
                        'seo_description' => $values['description'],
                        'focus_keyword' => $values['focus_keyword'],
                        'canonical_url' => $values['canonical'],
                    ]),
                'lms_course' => null,
                'store_product' => DB::table('store_product_translations')
                    ->where('product_id', $id)
                    ->where('locale', $locale)
                    ->update([
                        'meta_title' => $values['title'],
                        'meta_description' => $values['description'],
                    ]),
                'exhibition_artwork' => DB::table('exhibition_artwork_translations')
                    ->where('artwork_id', $id)
                    ->where('locale', $locale)
                    ->update([
                        'seo_title' => $this->limited($values['title'], 70),
                        'meta_description' => $this->limited($values['description'], 160),
                    ]),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function forget(string $type, int $id): void
    {
        $prefix = $type.':'.$id.':';

        foreach (array_keys($this->rowCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->rowCache[$key]);
            }
        }

        foreach (array_keys($this->legacyCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->legacyCache[$key]);
            }
        }

        foreach (array_keys($this->noindexCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->noindexCache[$key]);
            }
        }
    }

    private function locale(?string $locale): string
    {
        $locale = strtolower(trim((string) ($locale ?? '')));

        return $locale !== '' ? $locale : PlatformLocale::DEFAULT;
    }

    private function isDefaultLocale(string $locale): bool
    {
        return $locale === strtolower(PlatformLocale::DEFAULT);
    }

    private function normalizeRobots(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['index', 'noindex'], true) ? $value : '';
    }

    private function firstCustom(mixed $rowValue, string $legacy): string
    {
        $value = trim((string) ($rowValue ?? ''));

        return $value !== '' ? $value : $legacy;
    }

    private function trimOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function limited(mixed $value, int $length): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function isEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
