<?php

namespace Modules\Store\Application\PageBuilder;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StorePageBuilderPageService
{
    public const CATEGORY = 'plugin-store-page';

    public const SLUG = 'store';

    public function __construct(private readonly PlatformLocale $locale) {}

    public function ensure(): ?string
    {
        if (! $this->isReady()) {
            return null;
        }

        $managed = $this->managedPage();

        if ($managed !== null) {
            $this->ensureTranslations((int) $managed->id);

            return (string) $managed->slug;
        }

        if (DB::table('platform_pages')->where('slug', self::SLUG)->exists()) {
            return null;
        }

        $now = now();
        $default = $this->locale->defaultCode();
        $content = $this->contentFor($default);
        $body = $content['body'];

        $pageId = DB::transaction(function () use ($now, $body, $content): ?int {
            if (DB::table('platform_pages')->where('slug', self::SLUG)->exists()) {
                return null;
            }

            return (int) DB::table('platform_pages')->insertGetId([
                'title' => $content['title'],
                'slug' => self::SLUG,
                'content_type' => 'page',
                'block_key' => null,
                'parent_id' => null,
                'category' => self::CATEGORY,
                'menu_label' => $content['title'],
                'show_in_menu' => true,
                'content' => $body,
                'html' => $body,
                'css' => null,
                'vvvebjs_html' => $this->document($body, $default),
                'status' => 'published',
                'sort_order' => 70,
                'seo_title' => $content['title'],
                'meta_description' => $content['meta_description'],
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        if ($pageId) {
            $this->ensureTranslations($pageId);
        }

        return $this->managedPage() !== null ? self::SLUG : null;
    }

    public function managedSlug(): ?string
    {
        return $this->isReady() ? $this->managedPage()?->slug : null;
    }

    public function purge(): void
    {
        if (! Schema::hasTable('platform_pages')) {
            return;
        }

        DB::table('platform_pages')->where('category', self::CATEGORY)->delete();
    }

    /**
     * Add missing language versions of the managed store page without touching
     * versions that already exist (administrator edits are preserved).
     */
    public function ensureTranslations(int $pageId): void
    {
        if (! Schema::hasTable('platform_page_translations')) {
            return;
        }

        $default = $this->locale->defaultCode();
        $now = now();

        foreach (array_keys($this->locale->languages()) as $code) {
            $code = (string) $code;

            if ($code === '' || $code === $default) {
                continue;
            }

            if (DB::table('platform_page_translations')->where('page_id', $pageId)->where('locale', $code)->exists()) {
                continue;
            }

            $content = $this->contentFor($code);

            DB::table('platform_page_translations')->insert([
                'page_id' => $pageId,
                'locale' => $code,
                'title' => $content['title'],
                'seo_title' => $content['title'],
                'meta_description' => $content['meta_description'],
                'vvvebjs_html' => $this->document($content['body'], $code),
                'html' => $content['body'],
                'css' => null,
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function managedPage(): ?object
    {
        return DB::table('platform_pages')
            ->where('category', self::CATEGORY)
            ->where('content_type', 'page')
            ->first(['id', 'slug']);
    }

    private function isReady(): bool
    {
        return Schema::hasTable('platform_pages')
            && Schema::hasColumns('platform_pages', [
                'title', 'slug', 'content_type', 'block_key', 'parent_id', 'category',
                'menu_label', 'show_in_menu', 'content', 'html', 'css', 'vvvebjs_html',
                'status', 'sort_order', 'seo_title', 'meta_description', 'published_at',
                'created_at', 'updated_at',
            ]);
    }

    /**
     * @return array{title: string, meta_description: string, body: string}
     */
    private function contentFor(string $locale): array
    {
        $isArabic = str_starts_with($locale, 'ar');

        return [
            'title' => $isArabic ? 'المتجر' : 'Store',
            'meta_description' => $isArabic
                ? 'تصفّح المنتجات المتاحة حسب الفئة والعلامة التجارية والوسم والسعر والتوافر.'
                : 'Browse available products by category, brand, tag, price, and availability.',
            'body' => $isArabic
                ? <<<'HTML'
<main class="z4-store-builder-page" data-store-builder-page>
    <section class="z4-store-builder-hero">
        <div class="z4-store-builder-hero__content">
            <p>سوق ART INPA</p>
            <h1>اكتشف منتجاتنا</h1>
            <div>تصفّح المنتجات المتاحة، وفلتر الكتالوج، واعرض التفاصيل، وأضف القطع إلى سلتك فورًا.</div>
        </div>
        <a class="z4-store-builder-hero__cart" href="/store/cart">
            <span>سلتك</span>
            <strong data-store-cart-count>0</strong>
        </a>
    </section>
    <div class="store-catalog-slot" data-store-catalog="1" data-store-catalog-columns="4" data-store-catalog-per-page="24" data-store-catalog-filters="1" data-store-catalog-sort="1"><span hidden>كتالوج المتجر</span></div>
</main>
HTML
                : <<<'HTML'
<main class="z4-store-builder-page" data-store-builder-page>
    <section class="z4-store-builder-hero">
        <div class="z4-store-builder-hero__content">
            <p>ART INPA MARKETPLACE</p>
            <h1>Discover our products</h1>
            <div>Browse available products, filter the catalog, preview details, and add items to your cart instantly.</div>
        </div>
        <a class="z4-store-builder-hero__cart" href="/store/cart">
            <span>Your cart</span>
            <strong data-store-cart-count>0</strong>
        </a>
    </section>
    <div class="store-catalog-slot" data-store-catalog="1" data-store-catalog-columns="4" data-store-catalog-per-page="24" data-store-catalog-filters="1" data-store-catalog-sort="1"><span hidden>Store catalog</span></div>
</main>
HTML,
        ];
    }

    private function document(string $body, string $locale): string
    {
        $safeLocale = htmlspecialchars($locale, ENT_QUOTES, 'UTF-8');
        $direction = $this->locale->direction($locale);
        $title = htmlspecialchars($this->contentFor($locale)['title'], ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="'.$safeLocale.'" dir="'.$direction.'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<base href="/page-builder-assets/demo/landing/">'
            .'<title>'.$title.'</title>'
            .'<link id="landing-css" href="css/style.bundle.css" rel="stylesheet">'
            .'<link id="vvvebjs-css" href="css/custom.css" rel="stylesheet">'
            .'</head><body class="page">'.$body.'</body></html>';
    }
}
