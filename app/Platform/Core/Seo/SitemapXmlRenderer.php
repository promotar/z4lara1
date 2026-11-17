<?php

namespace App\Platform\Core\Seo;

/**
 * Renders Sitemap XML consistently for the Core controller and the SEO plugin.
 */
final class SitemapXmlRenderer
{
    public function urlset(iterable $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($entries as $entry) {
            $xml .= $this->urlEntry(is_array($entry) ? $entry : []);
        }

        return $xml.'</urlset>';
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public function urlEntry(array $entry): string
    {
        $loc = trim((string) ($entry['loc'] ?? ''));

        if ($loc === '') {
            return '';
        }

        $xml = "  <url>\n";
        $xml .= '    <loc>'.$this->e($loc)."</loc>\n";

        $lastmod = trim((string) ($entry['lastmod'] ?? ''));

        if ($lastmod !== '') {
            $xml .= '    <lastmod>'.$this->e($this->normalizeLastmod($lastmod))."</lastmod>\n";
        }

        $changefreq = trim((string) ($entry['changefreq'] ?? ''));

        if ($changefreq !== '') {
            $xml .= '    <changefreq>'.$this->e($changefreq)."</changefreq>\n";
        }

        if (isset($entry['priority']) && is_numeric($entry['priority'])) {
            $xml .= '    <priority>'.number_format((float) $entry['priority'], 1, '.', '')."</priority>\n";
        }

        foreach ((array) ($entry['alternates'] ?? []) as $hreflang => $href) {
            $href = trim((string) $href);

            if ($href !== '') {
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.$this->e((string) $hreflang).'" href="'.$this->e($href)."\"/>\n";
            }
        }

        foreach ((array) ($entry['images'] ?? []) as $image) {
            $imageUrl = is_array($image) ? trim((string) ($image['loc'] ?? '')) : trim((string) $image);

            if ($imageUrl === '') {
                continue;
            }

            $xml .= "    <image:image>\n";
            $xml .= '      <image:loc>'.$this->e($imageUrl)."</image:loc>\n";
            $xml .= "    </image:image>\n";
        }

        return $xml."  </url>\n";
    }

    public function sitemapIndex(iterable $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($sitemaps as $sitemap) {
            $loc = trim((string) ($sitemap['loc'] ?? ''));

            if ($loc === '') {
                continue;
            }

            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>'.$this->e($loc)."</loc>\n";

            $lastmod = trim((string) ($sitemap['lastmod'] ?? ''));

            if ($lastmod !== '') {
                $xml .= '    <lastmod>'.$this->e($this->normalizeLastmod($lastmod))."</lastmod>\n";
            }

            $xml .= "  </sitemap>\n";
        }

        return $xml.'</sitemapindex>';
    }

    /**
     * Sitemap protocol (https://www.sitemaps.org/protocol.html) requires
     * W3C Datetime values. Providers may hand over "Y-m-d H:i:s" strings,
     * which are invalid and silently ignored by search engines.
     */
    public function normalizeLastmod(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
            return $value;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return $value;
        }
    }

    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8', false);
    }
}
