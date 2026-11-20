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
            return (string) $managed->slug;
        }

        if (DB::table('platform_pages')->where('slug', self::SLUG)->exists()) {
            return null;
        }

        $now = now();
        $body = $this->body();

        DB::transaction(function () use ($now, $body): void {
            if (DB::table('platform_pages')->where('slug', self::SLUG)->exists()) {
                return;
            }

            DB::table('platform_pages')->insert([
                'title' => 'Store',
                'slug' => self::SLUG,
                'content_type' => 'page',
                'block_key' => null,
                'parent_id' => null,
                'category' => self::CATEGORY,
                'menu_label' => 'Store',
                'show_in_menu' => true,
                'content' => $body,
                'html' => $body,
                'css' => null,
                'vvvebjs_html' => $this->document($body),
                'status' => 'published',
                'sort_order' => 70,
                'seo_title' => 'Store',
                'meta_description' => 'Browse available products by category, brand, tag, price, and availability.',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

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

    private function body(): string
    {
        return <<<'HTML'
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
HTML;
    }

    private function document(string $body): string
    {
        $locale = htmlspecialchars($this->locale->current(), ENT_QUOTES, 'UTF-8');
        $direction = $this->locale->direction($locale);

        return '<!DOCTYPE html><html lang="'.$locale.'" dir="'.$direction.'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<base href="/page-builder-assets/demo/landing/">'
            .'<title>Store</title>'
            .'<link id="landing-css" href="css/style.bundle.css" rel="stylesheet">'
            .'<link id="vvvebjs-css" href="css/custom.css" rel="stylesheet">'
            .'</head><body class="page">'.$body.'</body></html>';
    }
}
