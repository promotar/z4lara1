<?php

namespace App\Platform\Core\Seo;

use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures canonical/hreflang/robots tags exist for legacy pages that still
 * render their own head block, and removes them from error responses.
 */
final class SeoHeadInjector
{
    public function inject(Response $response, SeoData $seo): void
    {
        if (! method_exists($response, 'getContent')) {
            return;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return;
        }

        $html = $response->getContent();

        if (! is_string($html) || ! str_contains($html, '</head>')) {
            return;
        }

        if ($response->getStatusCode() >= 400) {
            $html = preg_replace('#<link[^>]*rel=["\']canonical["\'][^>]*>\s*#i', '', $html) ?? $html;
            $html = preg_replace('#<link[^>]*hreflang=["\'][^"\']+["\'][^>]*>\s*#i', '', $html) ?? $html;
            $html = $this->putRobots($html, 'noindex, follow');

            $response->setContent($html);
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return;
        }

        if (! empty($seo->extra['disable_title'])) {
            $html = preg_replace('#<title[^>]*>.*?</title>\s*#is', '', $html) ?? $html;
            $html = preg_replace(
                '#<meta[^>]+(?:property=["\']og:title["\']|name=["\']twitter:title["\'])[^>]*>\s*#i',
                '',
                $html,
            ) ?? $html;
        }

        if (! empty($seo->extra['disable_description'])) {
            $html = preg_replace('#<meta\s+name=["\']description["\'][^>]*>\s*#i', '', $html) ?? $html;
            $html = preg_replace(
                '#<meta[^>]+(?:property=["\']og:description["\']|name=["\']twitter:description["\'])[^>]*>\s*#i',
                '',
                $html,
            ) ?? $html;
        }

        if (! empty($seo->extra['disable_canonical'])) {
            $html = preg_replace('#<link[^>]*rel=["\']canonical["\'][^>]*>\s*#i', '', $html) ?? $html;
            $html = preg_replace('#<meta[^>]+property=["\']og:url["\'][^>]*>\s*#i', '', $html) ?? $html;
        }

        if (! empty($seo->extra['disable_robots'])) {
            $html = preg_replace('#<meta\s+name=["\']robots["\'][^>]*>\s*#i', '', $html) ?? $html;
        }

        if (! empty($seo->extra['disable_keywords'])) {
            $html = preg_replace('#<meta\s+name=["\']keywords["\'][^>]*>\s*#i', '', $html) ?? $html;
        }

        if (! $seo->ogEnabled) {
            $html = preg_replace(
                '#<meta[^>]+(?:property=["\']og:[^"\']+["\']|name=["\']twitter:[^"\']+["\'])[^>]*>\s*#i',
                '',
                $html,
            ) ?? $html;
        }

        $tags = '';

        $replaceTitle = (bool) ($seo->extra['replace_title'] ?? false);

        if ($seo->canonical && empty($seo->extra['disable_canonical'])) {
            $canonicalTag = '<link rel="canonical" href="'.$this->e((string) $seo->canonical).'">';

            if (preg_match('#<link[^>]*rel=["\']canonical["\']#i', $html) === 1) {
                // Core/SEO owns the canonical URL. Module views may render a
                // legacy unprefixed canonical, so replace it with the final
                // SeoData value instead of trusting the first tag on the page.
                $html = preg_replace('#<link[^>]*rel=["\']canonical["\'][^>]*>#i', $canonicalTag, $html, 1) ?? $html;
            } else {
                $tags .= $canonicalTag;
            }
        }

        if (
            $seo->description !== null
            && trim((string) $seo->description) !== ''
            && empty($seo->extra['disable_description'])
            && preg_match('#<meta\s+name=["\']description["\']#i', $html) !== 1
        ) {
            $tags .= '<meta name="description" content="'.$this->e((string) $seo->description).'">';
        }
        if ($seo->robots->isIndexable()) {
            $existingHreflang = [];

            if (preg_match_all('#<link[^>]*hreflang=["\']([^"\']+)["\'][^>]*>#i', $html, $matches)) {
                $existingHreflang = array_map('strtolower', $matches[1]);
            }

            if ($replaceTitle && $seo->alternates !== []) {
                $html = preg_replace('#<link[^>]*hreflang=["\'][^"\']+["\'][^>]*>\s*#i', '', $html) ?? $html;
                $existingHreflang = [];
            }

            // Trust module-declared alternates, but fill missing language and
            // x-default entries from the Core URL strategy.
            foreach ($seo->alternates as $hreflang => $href) {
                $hreflang = strtolower((string) $hreflang);

                if ($hreflang === '' || in_array($hreflang, $existingHreflang, true)) {
                    continue;
                }

                $tags .= '<link rel="alternate" hreflang="'.$this->e($hreflang).'" href="'.$this->e((string) $href).'">';
            }
        }

        if (! empty($seo->extra['robots_meta_override'])) {
            $html = $this->putRobots($html, (string) $seo->extra['robots_meta_override']);
        } elseif (empty($seo->extra['disable_robots'])) {
            if (preg_match('#<meta\s+name=["\']robots["\']#i', $html) !== 1) {
                $tags .= '<meta name="robots" content="'.$this->e($seo->robots->toMeta()).'">';
            } elseif (! $seo->robots->isIndexable()) {
                $html = $this->putRobots($html, $seo->robots->toMeta());
            }
        }

        if ($replaceTitle && filled($seo->title)) {
            if (preg_match('#<title[^>]*>.*?</title>#is', $html) === 1) {
                $html = preg_replace(
                    '#<title[^>]*>.*?</title>#is',
                    '<title>'.$this->e((string) $seo->title).'</title>',
                    $html,
                    1,
                ) ?? $html;
            } else {
                $tags .= '<title>'.$this->e((string) $seo->title).'</title>';
            }
        }

        if ($replaceTitle && filled($seo->description)) {
            $tag = '<meta name="description" content="'.$this->e((string) $seo->description).'">';

            if (preg_match('#<meta\s+name=["\']description["\'][^>]*>#i', $html) === 1) {
                $html = preg_replace('#<meta\s+name=["\']description["\'][^>]*>#i', $tag, $html, 1) ?? $html;
            } else {
                $tags .= $tag;
            }
        }

        foreach ($seo->meta as $name => $content) {
            $tags .= $this->metaName($html, (string) $name, (string) $content);
        }

        if (
            $seo->description !== null
            && trim((string) $seo->description) !== ''
            && empty($seo->extra['disable_description'])
            && preg_match('#<meta\s+name=["\']description["\']#i', $html) !== 1
        ) {
            $tags .= '<meta name="description" content="'.$this->e((string) $seo->description).'">';
        }
        if ($seo->robots->isIndexable()) {
            $tags .= $this->socialTags($html, $seo);
            $tags .= $this->schemaTags($html, $seo);
        }

        if ($tags !== '') {
            // Format one tag per line so the injected block stays readable
            // in view-source.
            $tags = (string) preg_replace('#>(?=<(?:title|meta|link|script)\b)#i', ">\n", $tags);

            // Put generated SEO tags at the top of <head> so they appear in
            // view-source and are parsed before large inline style blocks.
            // Keep <meta charset> first for correct encoding detection, then the
            // generated SEO tags, then the rest of the head.
            if (preg_match('#<meta\s+charset=["\']?[^"\'>\s]+["\']?\s*/?>#i', $html, $charset) === 1) {
                $html = str_replace($charset[0], $charset[0]."\n".$tags, $html);
            } elseif (preg_match('#<head[^>]*>#i', $html, $match) === 1) {
                $html = str_replace($match[0], $match[0]."\n".$tags, $html);
            } else {
                $html = str_replace('</head>', $tags.'</head>', $html);
            }
        }

        $html = $this->applyImageSeo($html, $seo);

        $response->setContent($html);

        if (! $seo->robots->isIndexable()) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }
    }

    private function applyImageSeo(string $html, SeoData $seo): string
    {
        $title = trim((string) ($seo->extra['image_seo_title'] ?? ''));
        $description = trim((string) ($seo->extra['image_seo_description'] ?? ''));

        if ($title === '' && $description === '') {
            return $html;
        }

        $pattern = '#<main\b[^>]*>.*?</main>#is';

        if (preg_match($pattern, $html) !== 1) {
            $pattern = '#<article\b[^>]*>.*?</article>#is';
        }

        return preg_replace_callback($pattern, function (array $match) use ($title, $description): string {
            return preg_replace_callback('#<img\b[^>]*>#i', function (array $image) use ($title, $description): string {
                $tag = $image[0];

                if ($title !== '') {
                    if (preg_match('#\balt=(["\'])(.*?)\1#is', $tag, $alt) !== 1) {
                        $tag = $this->appendImageAttribute($tag, 'alt', $title);
                    } elseif (trim($alt[2]) === '') {
                        $tag = preg_replace('#\balt=(["\'])\1#is', 'alt="'.$this->e($title).'"', $tag, 1) ?? $tag;
                    }
                }

                if ($description !== '' && preg_match('#\btitle=(["\'])(.*?)\1#is', $tag) !== 1) {
                    $tag = $this->appendImageAttribute($tag, 'title', $description);
                }

                return $tag;
            }, $match[0]) ?? $match[0];
        }, $html) ?? $html;
    }

    private function appendImageAttribute(string $tag, string $name, string $value): string
    {
        $attribute = ' '.$name.'="'.$this->e($value).'"';

        if (str_ends_with($tag, '/>')) {
            return substr($tag, 0, -2).$attribute.'/>';
        }

        return substr($tag, 0, -1).$attribute.'>';
    }

    private function socialTags(string &$html, SeoData $seo): string
    {
        $tags = '';

        $tags .= $this->metaProperty($html, 'og:type', $seo->ogType);
        $tags .= $this->metaProperty($html, 'og:site_name', $seo->siteName);
        $tags .= $this->putMetaProperty($html, 'og:title', $seo->ogTitle ?: $seo->title);
        $tags .= $this->putMetaProperty($html, 'og:description', $seo->ogDescription ?: $seo->description);
        $tags .= $this->metaProperty($html, 'og:url', $seo->canonical);
        $tags .= $this->metaProperty($html, 'og:image', $seo->image);

        if (filled($seo->image)) {
            $tags .= $this->metaProperty($html, 'og:image:alt', $this->plain($seo->imageAlt));
            $tags .= $this->metaProperty($html, 'og:image:description', $this->plain($seo->extra['image_seo_description'] ?? null));
        }

        $tags .= $this->metaName($html, 'twitter:card', $seo->twitterCard);
        $tags .= $this->putMetaName($html, 'twitter:title', $seo->twitterTitle ?: $seo->ogTitle);
        $tags .= $this->putMetaName($html, 'twitter:description', $seo->twitterDescription ?: $seo->ogDescription);
        $tags .= $this->metaName($html, 'twitter:image', $seo->twitterImage ?: $seo->image);

        if (filled($seo->image)) {
            $tags .= $this->metaName($html, 'twitter:image:alt', $seo->imageAlt);
        }

        return $tags;
    }

    private function putMetaProperty(string &$html, string $property, ?string $content): string
    {
        if (! filled($content)) {
            return '';
        }

        $tag = '<meta property="'.$this->e($property).'" content="'.$this->e((string) $content).'">';

        if (preg_match('#<meta[^>]+property=["\']'.preg_quote($property, '#').'["\'][^>]*>#i', $html) === 1) {
            $html = preg_replace(
                '#<meta[^>]+property=["\']'.preg_quote($property, '#').'["\'][^>]*>#i',
                $tag,
                $html,
                1,
            ) ?? $html;

            return '';
        }

        return $tag;
    }

    private function putMetaName(string &$html, string $name, ?string $content): string
    {
        if (! filled($content)) {
            return '';
        }

        $tag = '<meta name="'.$this->e($name).'" content="'.$this->e((string) $content).'">';

        if (preg_match('#<meta[^>]+name=["\']'.preg_quote($name, '#').'["\'][^>]*>#i', $html) === 1) {
            $html = preg_replace(
                '#<meta[^>]+name=["\']'.preg_quote($name, '#').'["\'][^>]*>#i',
                $tag,
                $html,
                1,
            ) ?? $html;

            return '';
        }

        return $tag;
    }

    private function metaProperty(string $html, string $property, ?string $content): string
    {
        if (! filled($content) || preg_match('#<meta[^>]+property=["\']'.preg_quote($property, '#').'["\']#i', $html) === 1) {
            return '';
        }

        return '<meta property="'.$this->e($property).'" content="'.$this->e((string) $content).'">';
    }

    private function metaName(string $html, string $name, ?string $content): string
    {
        if (! filled($content) || preg_match('#<meta[^>]+name=["\']'.preg_quote($name, '#').'["\']#i', $html) === 1) {
            return '';
        }

        return '<meta name="'.$this->e($name).'" content="'.$this->e((string) $content).'">';
    }

    private function schemaTags(string $html, SeoData $seo): string
    {
        if ($seo->schema === [] || preg_match('#<script[^>]+application/ld\+json#i', $html) === 1) {
            return '';
        }

        $tags = '';

        foreach ($seo->schema as $schema) {
            if (! is_array($schema) || $schema === []) {
                continue;
            }

            $encoded = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($encoded)) {
                $tags .= '<script type="application/ld+json">'.$encoded.'</script>';
            }
        }

        return $tags;
    }

    private function putRobots(string $html, string $value): string
    {
        $tag = '<meta name="robots" content="'.$this->e($value).'">';

        if (preg_match('#<meta\s+name=["\']robots["\'][^>]*>#i', $html) === 1) {
            return preg_replace('#<meta\s+name=["\']robots["\'][^>]*>#i', $tag, $html, 1) ?? $html;
        }

        return str_replace('</head>', $tag.'</head>', $html);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Collapse a free-text value (may contain HTML/newlines) into one short line.
     */
    private function plain(mixed $value, int $limit = 200): ?string
    {
        $text = trim((string) preg_replace(
            '/\s+/',
            ' ',
            strip_tags((string) $value)
        ));

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)).'…' : $text;
    }
}
