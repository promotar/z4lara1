<?php

namespace App\Platform\Core\Rendering;

use App\Models\User;
use App\Platform\Core\Contracts\LatestContentProvider;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Menus\MenuManager;
use App\Platform\Core\Models\Menu;
use App\Platform\Core\Services\PluginOwnedPageGuard;
use App\Platform\Core\Services\PluginRuntimeGate;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PlatformContentRenderer
{
    private int $menuInstance = 0;

    private bool $menuStylesRendered = false;

    public function __construct(
        private readonly MenuManager $menus,
        private readonly SettingsRepository $settings,
        private readonly PluginRuntimeGate $pluginRuntime,
        private readonly LatestContentProvider $latestContent,
        private readonly ?PluginOwnedPageGuard $pluginPages = null,
        private readonly ?HookManager $hooks = null,
    ) {
        //
    }

    /**
     * @return Collection<int, object>
     */
    public function publishedLayoutSections(string $type): Collection
    {
        if (
            ! in_array($type, ['header', 'footer'], true)
            || ! Schema::hasTable('platform_pages')
            || ! Schema::hasColumn('platform_pages', 'content_type')
        ) {
            return collect();
        }

        return DB::table('platform_pages')
            ->where('content_type', $type)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'html', 'content', 'css'])
            ->map(function (object $section) use ($type): object {
                $section->content_type = $type;
                $section->rendered_html = $this->renderHtml((string) ($section->html ?: $section->content));

                return $section;
            });
    }

    public function layoutCss(): string
    {
        if (
            ! Schema::hasTable('platform_pages')
            || ! Schema::hasColumn('platform_pages', 'content_type')
        ) {
            return $this->themeModeCss();
        }

        $legacyCss = trim(DB::table('platform_pages')
            ->whereIn('content_type', ['header', 'footer'])
            ->where('status', 'published')
            ->orderByRaw("CASE content_type WHEN 'header' THEN 1 WHEN 'footer' THEN 2 ELSE 9 END")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('css')
            ->filter()
            ->implode("\n"));

        return trim(collect([$this->themeModeCss(), $legacyCss])
            ->filter(fn (string $css): bool => trim($css) !== '')
            ->implode("\n"));
    }

    public function themeModeCss(): string
    {
        $values = $this->values();
        $lightBackground = $this->themeColor($values['theme.light_background'] ?? null, '#ffffff');
        $lightSurface = $this->themeColor($values['theme.light_surface'] ?? null, '#ffffff');
        $lightText = $this->themeColor($values['theme.light_text'] ?? null, '#111827');
        $lightMutedText = $this->themeColor($values['theme.light_muted_text'] ?? null, '#4b5563');
        $darkBackground = $this->themeColor($values['theme.dark_background'] ?? null, '#0f172a');
        $darkSurface = $this->themeColor($values['theme.dark_surface'] ?? null, '#111827');
        $darkText = $this->themeColor($values['theme.dark_text'] ?? null, '#f9fafb');
        $darkMutedText = $this->themeColor($values['theme.dark_muted_text'] ?? null, '#cbd5e1');
        $accent = $this->themeColor($values['theme.accent_color'] ?? null, '#df0000');

        return <<<CSS
:root {
  --art-color-background: {$lightBackground};
  --art-color-surface: {$lightSurface};
  --art-color-text: {$lightText};
  --art-color-muted: {$lightMutedText};
  --art-color-accent: {$accent};
}

html.art-dark-mode {
  --art-color-background: {$darkBackground};
  --art-color-surface: {$darkSurface};
  --art-color-text: {$darkText};
  --art-color-muted: {$darkMutedText};
}

body {
  background: var(--art-color-background) !important;
  color: var(--art-color-text);
}

html.art-dark-mode body {
  background: var(--art-color-background) !important;
  color: var(--art-color-text) !important;
}

body main,
body [data-platform-page],
body .page-builder-output,
body .art-page,
body .art-content,
body .art-section,
body .art-card,
body .art-theme-footer,
body .art-theme-header {
  color: var(--art-color-text);
}

html.art-dark-mode body main,
html.art-dark-mode body [data-platform-page],
html.art-dark-mode body .page-builder-output {
  background: var(--art-color-background) !important;
  color: var(--art-color-text) !important;
}

html.art-dark-mode body main,
html.art-dark-mode body [data-platform-page],
html.art-dark-mode body .page-builder-output,
html.art-dark-mode body .art-page,
html.art-dark-mode body .art-content {
  background-color: var(--art-color-background) !important;
}

html.art-dark-mode body .art-theme-header,
html.art-dark-mode body .art-theme-footer {
  color: var(--art-color-text);
}

html.art-dark-mode body [data-platform-page] > section,
html.art-dark-mode body .page-builder-output > section {
  border-color: rgba(255, 255, 255, 0.12);
}

html.art-dark-mode body p,
html.art-dark-mode body span,
html.art-dark-mode body li,
html.art-dark-mode body h1,
html.art-dark-mode body h2,
html.art-dark-mode body h3,
html.art-dark-mode body h4,
html.art-dark-mode body h5,
html.art-dark-mode body h6,
html.art-dark-mode body small,
html.art-dark-mode body strong {
  color: inherit;
}

html.art-dark-mode body a {
  color: inherit;
}

html.art-dark-mode body input,
html.art-dark-mode body textarea,
html.art-dark-mode body select {
  background-color: var(--art-color-surface) !important;
  color: var(--art-color-text) !important;
  border-color: rgba(255, 255, 255, 0.18) !important;
}
CSS;
    }

    public function renderHtml(?string $html): string
    {
        $html = (string) $html;

        if ($html === '') {
            return '';
        }

        $html = $this->renderSiteIconPlaceholders($html);
        $html = $this->renderLogoPlaceholders($html);
        $html = $this->renderNewsTickerPlaceholders($html);
        $html = $this->renderLatestMegaPlaceholders($html);
        $html = $this->renderMenuPlaceholders($html);
        $html = $this->removeUnavailablePluginNavigation($html);
        $html = $this->renderImageActions($html);

        return $html;
    }

    private function removeUnavailablePluginNavigation(string $html): string
    {
        $html = preg_replace_callback(
            '~<a\b(?P<attrs>[^>]*)\shref\s*=\s*(["\x27])(?P<href>.*?)\2(?P<attrs2>[^>]*)>(?P<body>.*?)</a>~is',
            function (array $matches): string {
                $href = html_entity_decode(trim((string) ($matches['href'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $this->pluginPages()->isNavigationAvailable(null, $href)
                    ? (string) $matches[0]
                    : '';
            },
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '~<form\b(?P<attrs>[^>]*)\saction\s*=\s*(["\x27])(?P<action>.*?)\2(?P<attrs2>[^>]*)>(?P<body>.*?)</form>~is',
            function (array $matches): string {
                $action = html_entity_decode(trim((string) ($matches['action'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $this->pluginPages()->isNavigationAvailable(null, $action)
                    ? (string) $matches[0]
                    : '';
            },
            $html,
        ) ?? $html;

        return $this->pluginRuntime->allows('blog')
            ? $html
            : $this->removeDisabledBlogInterface($html);
    }

    private function removeDisabledBlogInterface(string $html): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $rootId = 'platform-content-root-'.bin2hex(random_bytes(6));
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="'.$rootId.'">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        if (! $loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//*[@id="'.$rootId.'"]')->item(0);
        if (! $root instanceof \DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            return $html;
        }

        $queries = [
            '//*[contains(concat(" ", normalize-space(@class), " "), " art-header-latest-menu ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " art-header-ticker ")]',
            '//*[@id="art-header-search"]',
            '//a[@href="#art-header-search"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " art-footer-column ")][.//nav[contains(concat(" ", normalize-space(@class), " "), " art-footer-menu ")] and count(.//a)=0]',
        ];

        $nodes = [];
        foreach ($queries as $query) {
            $matches = $xpath->query($query);
            if ($matches === false) {
                continue;
            }
            foreach ($matches as $node) {
                $nodes[spl_object_id($node)] = $node;
            }
        }

        foreach (array_reverse($nodes) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $rendered = '';
        foreach ($root->childNodes as $child) {
            $rendered .= (string) $dom->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return $rendered;
    }

    private function pluginPages(): PluginOwnedPageGuard
    {
        return $this->pluginPages ?? app(PluginOwnedPageGuard::class);
    }

    /**
     * @return array<int, array{value: string, name: string}>
     */
    public function menuTraitOptions(): array
    {
        return collect($this->menuOptions())
            ->map(fn (string $name, string $value): array => [
                'value' => $value,
                'name' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array{label: string, href: string|null, target: string}>>
     */
    public function menuPreviewItems(?User $user = null): array
    {
        return collect(array_keys($this->menuOptions()))
            ->mapWithKeys(fn (string $key): array => [
                $key => collect($this->menus->getFrontendMenuByKey($key, $user))
                    ->map(fn (array $item): array => $this->menuPreviewItem($item))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, href: string|null, target: string, children: array<int, mixed>}
     */
    private function menuPreviewItem(array $item): array
    {
        return [
            'label' => (string) ($item['label'] ?: $item['title']),
            'href' => $this->menuItemHref($item),
            'target' => (string) ($item['target'] ?? '_self'),
            'children' => collect($item['children'] ?? [])
                ->map(fn (array $child): array => $this->menuPreviewItem($child))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function menuOptions(): array
    {
        if (! Schema::hasTable('menus')) {
            return [];
        }

        return Menu::query()
            ->where('location', 'frontend')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['key', 'name', 'is_active'])
            ->mapWithKeys(fn (Menu $menu): array => [
                $menu->key => $menu->name.($menu->is_active ? '' : ' (Inactive)'),
            ])
            ->all();
    }

    public function defaultMenuKey(): string
    {
        return (string) (array_key_first($this->menuOptions()) ?: 'platform.frontend');
    }

    public function siteLogo(): ?string
    {
        $logo = $this->values()['general.site_logo'] ?? null;

        return is_string($logo) && trim($logo) !== '' ? $logo : null;
    }

    public function siteIcon(): ?string
    {
        $values = $this->values();
        $icon = $values['general.site_icon'] ?? null;
        $logo = $values['general.site_logo'] ?? null;

        if (is_string($icon) && trim($icon) !== '') {
            return $icon;
        }

        return is_string($logo) && trim($logo) !== '' ? $logo : null;
    }

    public function siteTitle(): string
    {
        $title = $this->values()['general.site_title'] ?? config('app.name', 'Laravel');

        return is_string($title) && trim($title) !== '' ? $title : config('app.name', 'Laravel');
    }

    private function renderSiteIconPlaceholders(string $html): string
    {
        return preg_replace_callback(
            '~<(?P<tag>[a-z][a-z0-9:-]*)(?P<attrs>[^>]*)\sdata-platform-site-icon(?:=(["\']).*?\3)?(?P<attrs2>[^>]*)>(?P<body>.*?)</\1>~is',
            function (array $matches): string {
                $attrs = ($matches['attrs'] ?? '').' '.($matches['attrs2'] ?? '');
                $preservedAttributes = $this->safeAttributes($attrs, ['href', 'data-platform-site-icon']);
                $imageAttributes = $this->imageAttributeTemplate((string) ($matches['body'] ?? ''));
                $preservedImageAttributes = $this->safeAttributes($imageAttributes, ['src', 'alt']);
                $icon = $this->siteIcon();
                $title = $this->siteTitle();

                $content = $icon
                    ? '<img src="'.e($icon).'" alt="'.e($title).'"'.$preservedImageAttributes.'>'
                    : e($title);

                return '<a href="'.e(url('/')).'" data-platform-site-icon="favicon"'.$preservedAttributes.'>'.$content.'</a>';
            },
            $html,
        ) ?? $html;
    }

    private function renderLogoPlaceholders(string $html): string
    {
        return preg_replace_callback(
            '~<(?P<tag>[a-z][a-z0-9:-]*)(?P<attrs>[^>]*)\sdata-platform-logo(?:=(["\']).*?\3)?(?P<attrs2>[^>]*)>(?P<body>.*?)</\1>~is',
            function (array $matches): string {
                $attrs = ($matches['attrs'] ?? '').' '.($matches['attrs2'] ?? '');
                $preservedAttributes = $this->safeAttributes($attrs, ['href', 'data-platform-logo']);
                $imageAttributes = $this->imageAttributeTemplate((string) ($matches['body'] ?? ''));
                $preservedImageAttributes = $this->safeAttributes($imageAttributes, ['src', 'alt']);
                $logo = $this->siteLogo();
                $title = $this->siteTitle();

                if ($this->attributeValue($imageAttributes, 'style') === null) {
                    $preservedImageAttributes .= ' style="max-height:64px;width:auto;"';
                }

                $content = $logo
                    ? '<img src="'.e($logo).'" alt="'.e($title).'"'.$preservedImageAttributes.'>'
                    : e($title);

                return '<a href="'.e(url('/')).'" data-platform-logo="site"'.$preservedAttributes.'>'.$content.'</a>';
            },
            $html,
        ) ?? $html;
    }

    private function renderMenuPlaceholders(string $html): string
    {
        return preg_replace_callback(
            '~<(?P<tag>[a-z][a-z0-9:-]*)(?P<attrs>[^>]*)\sdata-platform-menu-key=(["\'])(?P<key>.*?)\3(?P<attrs2>[^>]*)>(?P<body>.*?)</\1>~is',
            function (array $matches): string {
                $attrs = ($matches['attrs'] ?? '').' '.($matches['attrs2'] ?? '');
                $preservedAttributes = $this->safeAttributes($attrs, [
                    'data-platform-menu-key',
                    'data-platform-menu-layout',
                    'data-platform-menu-icon',
                    'data-platform-menu-side',
                    'data-platform-menu-font-family',
                    'data-platform-menu-font-size',
                    'data-platform-menu-font-weight',
                    'data-platform-menu-text-color',
                    'data-platform-menu-background',
                    'data-platform-menu-hover-color',
                    'data-platform-menu-hover-background',
                    'data-platform-menu-submenu-background',
                    'data-platform-menu-item-margin',
                    'data-platform-menu-item-padding',
                    'data-platform-menu-offcanvas-width',
                    'style',
                ]);
                $linkTemplates = $this->anchorAttributeTemplates((string) ($matches['body'] ?? ''));
                $key = trim((string) ($matches['key'] ?? '')) ?: $this->defaultMenuKey();
                $items = $this->menus->getFrontendMenuByKey($key, auth()->user());

                return $this->menuHtml($key, $items, $preservedAttributes, $linkTemplates, $attrs);
            },
            $html,
        ) ?? $html;
    }

    private function renderNewsTickerPlaceholders(string $html): string
    {
        return preg_replace_callback(
            '~<(?P<tag>[a-z][a-z0-9:-]*)(?P<attrs>[^>]*)\sdata-platform-news-ticker=(["\'])(?P<source>.*?)\3(?P<attrs2>[^>]*)>(?P<body>.*?)</\1>~is',
            function (array $matches): string {
                if (! $this->latestContent->available()) {
                    return '';
                }

                $attrs = ($matches['attrs'] ?? '').' '.($matches['attrs2'] ?? '');
                $preservedAttributes = $this->safeAttributes($attrs, ['data-platform-news-ticker', 'data-platform-news-limit']);
                $linkTemplates = $this->anchorAttributeTemplates((string) ($matches['body'] ?? ''));
                $limit = max(1, min(12, (int) ($this->attributeValue($attrs, 'data-platform-news-limit') ?: 6)));
                $posts = $this->latestTickerPosts($limit);

                if ($posts->isEmpty()) {
                    return '<'.$matches['tag'].' data-platform-news-ticker="latest-posts"'.$preservedAttributes.'>'
                        .(string) ($matches['body'] ?? '')
                        .'</'.$matches['tag'].'>';
                }

                $links = $posts
                    ->map(function (object $post, int $index) use ($linkTemplates): string {
                        $href = Route::has('blog.show') ? route('blog.show', $post->slug) : url('/blog/'.$post->slug);
                        $linkAttributes = $this->linkAttributesForIndex($linkTemplates, $index);

                        return '<a href="'.e($href).'"'.$linkAttributes.'>'.e((string) $post->title).'</a>';
                    })
                    ->implode('');

                return '<'.$matches['tag'].' data-platform-news-ticker="latest-posts"'.$preservedAttributes.'>'
                    .$links
                    .'</'.$matches['tag'].'>';
            },
            $html,
        ) ?? $html;
    }

    private function renderLatestMegaPlaceholders(string $html): string
    {
        return preg_replace_callback(
            '~<(?P<tag>[a-z][a-z0-9:-]*)(?P<attrs>[^>]*)\sdata-platform-latest-mega=(["\'])(?P<source>.*?)\3(?P<attrs2>[^>]*)>(?P<body>.*?)</\1>~is',
            function (array $matches): string {
                if (! $this->latestContent->available()) {
                    return '';
                }

                $attrs = ($matches['attrs'] ?? '').' '.($matches['attrs2'] ?? '');
                $preservedAttributes = $this->safeAttributes($attrs, ['data-platform-latest-mega', 'data-platform-news-limit']);
                $limit = max(4, min(12, (int) ($this->attributeValue($attrs, 'data-platform-news-limit') ?: 8)));
                $posts = $this->latestTickerPosts($limit);

                if ($posts->isEmpty()) {
                    return '<'.$matches['tag'].' data-platform-latest-mega="latest-posts"'.$preservedAttributes.'>'
                        .(string) ($matches['body'] ?? '')
                        .'</'.$matches['tag'].'>';
                }

                $cards = $posts
                    ->map(function (object $post): string {
                        $href = Route::has('blog.show') ? route('blog.show', $post->slug) : url('/blog/'.$post->slug);
                        $image = $this->safeFrontendUrl((string) ($post->featured_image ?? '')) ?: $this->siteIcon();
                        $alt = (string) ($post->featured_image_alt ?? $post->title);
                        $imageHtml = $image
                            ? '<img src="'.e($image).'" alt="'.e($alt).'">'
                            : '<span class="art-header-mega-card__placeholder"></span>';

                        return '<a class="art-header-mega-card" href="'.e($href).'">'
                            .'<span class="art-header-mega-card__image">'.$imageHtml.'</span>'
                            .'<span class="art-header-mega-card__title">'.e((string) $post->title).'</span>'
                            .'</a>';
                    })
                    ->implode('');

                return '<'.$matches['tag'].' data-platform-latest-mega="latest-posts"'.$preservedAttributes.'>'
                    .'<div class="art-header-mega__viewport"><div class="art-header-mega__track">'.$cards.'</div></div>'
                    .'</'.$matches['tag'].'>';
            },
            $html,
        ) ?? $html;
    }

    /**
     * @return Collection<int, object>
     */
    private function latestTickerPosts(int $limit): Collection
    {
        return $this->latestContent->latest($limit);
    }

    private function renderImageActions(string $html): string
    {
        return preg_replace_callback(
            '/<img\b(?P<attrs>[^>]*)>/is',
            function (array $matches): string {
                $attrs = (string) ($matches['attrs'] ?? '');
                $action = $this->canonicalImageAction($this->attributeValue($attrs, 'data-pb-image-action'));

                if (! in_array($action, ['link', 'lightbox'], true)) {
                    return (string) $matches[0];
                }

                $src = $this->safeFrontendUrl($this->attributeValue($attrs, 'src') ?? $this->attributeValue($attrs, 'data-pb-src'));

                if ($src === null) {
                    return (string) $matches[0];
                }

                $image = '<img src="'.e($src).'"'.$this->safeAttributes($attrs, [
                    'src',
                    'data-pb-image-action',
                    'data-pb-link-url',
                    'data-pb-link-target',
                    'data-pb-lightbox-size',
                ]).'>';

                if ($action === 'link') {
                    $href = $this->safeFrontendUrl($this->attributeValue($attrs, 'data-pb-link-url'));

                    if ($href === null) {
                        return $image;
                    }

                    $target = $this->attributeValue($attrs, 'data-pb-link-target') === '_blank' ? ' target="_blank" rel="noopener"' : '';

                    return '<a href="'.e($href).'" class="pb-image-action pb-image-link"'.$target.'>'.$image.'</a>';
                }

                $size = $this->attributeValue($attrs, 'data-pb-lightbox-size') === 'full' ? 'full' : 'contain';

                return '<a href="'.e($src).'" class="pb-image-action pb-image-lightbox" data-pb-lightbox-trigger="image" data-pb-lightbox-size="'.e($size).'">'.$image.'</a>';
            },
            $html,
        ) ?? $html;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, string|null>>  $linkTemplates
     */
    private function menuHtml(
        string $key,
        array $items,
        string $preservedAttributes = '',
        array $linkTemplates = [],
        string $sourceAttributes = '',
    ): string {
        $links = collect($items)
            ->map(fn (array $item, int $index): string => $this->menuItemHtml($item, $index, $linkTemplates))
            ->filter()
            ->implode('');
        $layout = $this->menuChoice($sourceAttributes, 'data-platform-menu-layout', ['horizontal', 'vertical', 'offcanvas'], 'horizontal');
        $icon = $this->menuChoice($sourceAttributes, 'data-platform-menu-icon', ['bars', 'compact', 'dots', 'grid'], 'bars');
        $side = $this->menuChoice($sourceAttributes, 'data-platform-menu-side', ['start', 'end'], 'end');
        $style = $this->menuPresentationStyle($sourceAttributes);
        $instance = ++$this->menuInstance;
        $toggleId = 'platform-menu-toggle-'.$instance.'-'.substr(sha1($key), 0, 8);
        $itemsHtml = '<div class="platform-menu-items">'.$links.'</div>';

        if ($layout === 'offcanvas') {
            $body = '<input class="platform-menu-toggle-control" type="checkbox" id="'.e($toggleId).'">'
                .'<label class="platform-menu-toggle" for="'.e($toggleId).'" aria-label="Open menu">'.$this->menuToggleIcon($icon).'</label>'
                .'<label class="platform-menu-overlay" for="'.e($toggleId).'" aria-label="Close menu"></label>'
                .'<div class="platform-menu-surface">'
                .'<label class="platform-menu-close" for="'.e($toggleId).'" aria-label="Close menu">&times;</label>'
                .$itemsHtml
                .'</div>';
        } else {
            $body = '<div class="platform-menu-surface">'.$itemsHtml.'</div>';
        }

        $stylesheet = '';
        if (! $this->menuStylesRendered) {
            $stylesheet = '<link rel="stylesheet" href="'.e(url('/page-builder-assets/v5/integration/css/frontend-menu.css')).'" data-platform-menu-styles="1">';
            $this->menuStylesRendered = true;
        }

        $html = $stylesheet.'<nav data-platform-menu-key="'.e($key).'"'
            .' data-platform-menu-layout="'.e($layout).'"'
            .' data-platform-menu-icon="'.e($icon).'"'
            .' data-platform-menu-side="'.e($side).'"'
            .$this->menuPresentationAttributes($sourceAttributes)
            .$preservedAttributes
            .$this->optionalAttribute('style', $style)
            .'>'.$body.'</nav>';
        $filtered = ($this->hooks ?? app(HookManager::class))
            ->applyFilters('frontend.menu.html', $html, $key, $items);

        return is_string($filtered) ? $filtered : $html;
    }

    private function menuChoice(string $attributes, string $name, array $allowed, string $fallback): string
    {
        $value = strtolower(trim((string) $this->attributeValue($attributes, $name)));

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function menuToggleIcon(string $icon): string
    {
        return '<span class="platform-menu-icon platform-menu-icon-'.$icon.'" aria-hidden="true"><i></i><i></i><i></i><i></i></span>';
    }

    private function menuPresentationAttributes(string $attributes): string
    {
        return collect([
            'data-platform-menu-font-family',
            'data-platform-menu-font-size',
            'data-platform-menu-font-weight',
            'data-platform-menu-text-color',
            'data-platform-menu-background',
            'data-platform-menu-hover-color',
            'data-platform-menu-hover-background',
            'data-platform-menu-submenu-background',
            'data-platform-menu-item-margin',
            'data-platform-menu-item-padding',
            'data-platform-menu-offcanvas-width',
        ])->map(fn (string $name): string => $this->optionalAttribute($name, $this->attributeValue($attributes, $name)))->implode('');
    }

    private function menuPresentationStyle(string $attributes): string
    {
        $font = trim((string) $this->attributeValue($attributes, 'data-platform-menu-font-family'));
        $font = in_array($font, ['inherit', 'system-ui', 'Arial', 'Helvetica', 'Tahoma', 'Verdana', 'Georgia', 'Times New Roman'], true) ? $font : 'inherit';
        $weight = $this->menuChoice($attributes, 'data-platform-menu-font-weight', ['400', '500', '600', '700'], '500');
        $baseStyle = trim((string) $this->attributeValue($attributes, 'style'));
        $declarations = array_filter([
            '--platform-menu-font-family:'.$font,
            '--platform-menu-font-size:'.$this->menuSize($attributes, 'data-platform-menu-font-size', '15px'),
            '--platform-menu-font-weight:'.$weight,
            '--platform-menu-color:'.$this->menuColor($attributes, 'data-platform-menu-text-color', '#1f2937'),
            '--platform-menu-background:'.$this->menuColor($attributes, 'data-platform-menu-background', 'transparent'),
            '--platform-menu-hover-color:'.$this->menuColor($attributes, 'data-platform-menu-hover-color', '#991b1b'),
            '--platform-menu-hover-background:'.$this->menuColor($attributes, 'data-platform-menu-hover-background', '#fef2f2'),
            '--platform-menu-submenu-background:'.$this->menuColor($attributes, 'data-platform-menu-submenu-background', '#ffffff'),
            '--platform-menu-item-margin:'.$this->menuSizeList($attributes, 'data-platform-menu-item-margin', '0'),
            '--platform-menu-item-padding:'.$this->menuSizeList($attributes, 'data-platform-menu-item-padding', '10px 14px'),
            '--platform-menu-offcanvas-width:'.$this->menuSize($attributes, 'data-platform-menu-offcanvas-width', '320px'),
        ]);

        return trim(rtrim($baseStyle, ';').';'.implode(';', $declarations), ';');
    }

    private function menuColor(string $attributes, string $name, string $fallback): string
    {
        $value = trim((string) $this->attributeValue($attributes, $name));

        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) === 1 || $value === 'transparent'
            ? $value
            : $fallback;
    }

    private function menuSize(string $attributes, string $name, string $fallback): string
    {
        $value = trim((string) $this->attributeValue($attributes, $name));

        return $this->validMenuSize($value) ? $value : $fallback;
    }

    private function menuSizeList(string $attributes, string $name, string $fallback): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->attributeValue($attributes, $name))) ?: [];

        return count($parts) >= 1 && count($parts) <= 4 && collect($parts)->every(fn (string $part): bool => $this->validMenuSize($part))
            ? implode(' ', $parts)
            : $fallback;
    }

    private function validMenuSize(string $value): bool
    {
        return preg_match('/^(0|-?[0-9]{1,4}(px|rem|em|%|vw|vh))$/', trim($value)) === 1;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array<string, string|null>>  $linkTemplates
     */
    private function menuItemHtml(array $item, int $index = 0, array $linkTemplates = []): string
    {
        $href = $this->menuItemHref($item);
        $children = collect($item['children'] ?? [])
            ->map(fn (array $child, int $childIndex): string => $this->menuItemHtml($child, $childIndex))
            ->filter()
            ->implode('');

        if ($href === null && $children === '') {
            return '';
        }

        $label = (string) ($item['label'] ?: $item['title']);
        $target = ($item['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
        $linkAttributes = $this->linkAttributesForIndex($linkTemplates, $index);
        $link = $href !== null
            ? '<a href="'.e($href).'"'.$target.$linkAttributes.'>'.e($label).'</a>'
            : '<span class="platform-menu-link"'.$linkAttributes.'>'.e($label).'</span>';

        if ($children === '') {
            return $link;
        }

        return '<span class="platform-menu-item platform-menu-item-has-children">'
            .$link
            .'<span class="platform-submenu">'.$children.'</span>'
            .'</span>';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function menuItemHref(array $item): ?string
    {
        $routeName = $item['route_name'] ?? null;
        $url = $item['url'] ?? null;

        if (is_string($routeName) && Route::has($routeName)) {
            return route($routeName, $item['route_params'] ?? []);
        }

        if (is_string($url) && trim($url) !== '') {
            return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
                ? $url
                : url($url);
        }

        return null;
    }

    private function attributeValue(string $attrs, string $name): ?string
    {
        if (preg_match('/\s'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is', $attrs, $matches) !== 1) {
            return null;
        }

        return (string) $matches[2];
    }

    private function canonicalImageAction(?string $action): string
    {
        $action = strtolower(trim((string) $action));

        return match ($action) {
            'link', 'custom', 'custom_url' => 'link',
            'lightbox', 'media_file' => 'lightbox',
            default => 'none',
        };
    }

    private function safeFrontendUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
    }

    /**
     * @param  array<int, array<string, string|null>>  $templates
     */
    private function linkAttributesForIndex(array $templates, int $index): string
    {
        $attributes = $templates[$index] ?? $templates[0] ?? [];

        if ($index > 0 && ! array_key_exists($index, $templates)) {
            $attributes['id'] = null;
        }

        return $this->optionalAttribute('id', $attributes['id'] ?? null)
            .$this->optionalAttribute('class', $attributes['class'] ?? null)
            .$this->optionalAttribute('style', $attributes['style'] ?? null);
    }

    /**
     * @return array<int, array{id: string|null, class: string|null, style: string|null}>
     */
    private function anchorAttributeTemplates(string $html): array
    {
        if (preg_match_all('/<a\b(?P<attrs>[^>]*)>/is', $html, $matches) !== false && $matches['attrs'] !== []) {
            return collect($matches['attrs'])
                ->map(fn (string $attrs): array => [
                    'id' => $this->attributeValue($attrs, 'id'),
                    'class' => $this->attributeValue($attrs, 'class'),
                    'style' => $this->attributeValue($attrs, 'style'),
                ])
                ->all();
        }

        return [];
    }

    private function imageAttributeTemplate(string $html): string
    {
        if (preg_match('/<img\b(?P<attrs>[^>]*)>/is', $html, $matches) !== 1) {
            return '';
        }

        return (string) ($matches['attrs'] ?? '');
    }

    /**
     * @param  array<int, string>  $excludedNames
     */
    private function safeAttributes(string $attrs, array $excludedNames = []): string
    {
        $excluded = array_fill_keys(array_map('strtolower', $excludedNames), true);
        $safe = [];

        if (preg_match_all('/\s([A-Za-z_:][-A-Za-z0-9_:.]*)\s*=\s*(["\'])(.*?)\2/is', $attrs, $matches, PREG_SET_ORDER) === false) {
            return '';
        }

        foreach ($matches as $match) {
            $name = strtolower((string) $match[1]);

            if (isset($excluded[$name]) || str_starts_with($name, 'on')) {
                continue;
            }

            $safe[$name] = (string) $match[3];
        }

        return collect($safe)
            ->map(fn (string $value, string $name): string => $this->optionalAttribute($name, $value))
            ->implode('');
    }

    private function optionalAttribute(string $name, ?string $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? '' : ' '.$name.'="'.e($value).'"';
    }

    private function themeColor(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtolower($value) : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        try {
            return $this->settings->values();
        } catch (\Throwable) {
            return [];
        }
    }
}
