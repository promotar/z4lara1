<?php

namespace Modules\Seo\Http\Controllers;

use App\Platform\Core\Seo\SitemapXmlRenderer;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Seo\Services\SeoSitemapProvider;

/**
 * Public sitemap endpoints owned by the SEO plugin.
 *
 * Core keeps serving /sitemap.xml (chunked). This controller adds the
 * Yoast-style index and per-content-type sitemaps without touching core:
 *   /sitemap_index.xml
 *   /{file}-sitemap.xml            (post, page, category, post_tag, product, ...)
 *   /{file}-sitemap{page}.xml      (pagination)
 *   /sitemap.xsl                   (browser stylesheet)
 */
final class SitemapSectionsController extends Controller
{
    public function __construct(
        private readonly SeoSitemapProvider $provider,
        private readonly SitemapXmlRenderer $renderer,
    ) {
    }

    public function index(): Response
    {
        $sections = $this->provider->sitemapSections();

        if ($sections === []) {
            abort(404);
        }

        $index = [];

        foreach ($sections as $section) {
            $file = (string) ($section['file'] ?? '');
            $pages = max(1, (int) ($section['pages'] ?? 1));
            $lastmod = $section['lastmod'] ?? null;

            for ($page = 1; $page <= $pages; $page++) {
                $index[] = [
                    'loc' => $page === 1
                        ? route('seo.sitemap.sections.section', ['file' => $file])
                        : route('seo.sitemap.sections.section.page', ['file' => $file, 'page' => $page]),
                    'lastmod' => $lastmod,
                ];
            }
        }

        return $this->xml($this->renderer->sitemapIndex($index));
    }

    public function section(string $file, int $page = 1): Response
    {
        $xml = $this->provider->sitemapSection($file, max(1, $page));
        abort_if($xml === null || trim($xml) === '', 404);

        return $this->xml($xml);
    }

    public function legacyChunk(int $chunk): Response
    {
        $xml = $this->provider->sitemapChunk($chunk);
        abort_if($xml === null || trim($xml) === '', 404);

        return $this->xml($xml);
    }

    public function stylesheet(): Response
    {
        return response($this->stylesheetMarkup(), 200, [
            'Content-Type' => 'text/xsl; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function xml(string $xml): Response
    {
        if (! str_contains($xml, 'xml-stylesheet')) {
            $xml = (string) preg_replace(
                '/^(<\?xml[^>]*\?>\s*)/',
                '$1<?xml-stylesheet type="text/xsl" href="/sitemap.xsl?v=4"?>'."\n",
                $xml,
                1
            );
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) config('seo.sitemap.cache_ttl', 300),
        ]);
    }

    private function stylesheetMarkup(): string
    {
        return <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:xhtml="http://www.w3.org/1999/xhtml"
    exclude-result-prefixes="sm xhtml">
    <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml" lang="en">
            <head>
                <meta charset="utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <title>XML Sitemap</title>
                <style>
                    html { background: #fff; }
                    body {
                        margin: 0; padding: 24px;
                        color: #333;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                        font-size: 14px; line-height: 1.5;
                    }
                    h1 { font-size: 22px; font-weight: 400; margin: 0 0 10px; }
                    p { margin: 0 0 10px; }
                    a { color: #0073aa; }
                    table { border-collapse: collapse; width: 100%; margin: 10px 0 24px; border: 1px solid #ddd; }
                    th, td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; word-break: break-word; }
                    thead th { background: #f2f2f2; font-weight: 600; }
                    tbody tr:nth-child(even) td { background: #fafafa; }
                </style>
            </head>
            <body>
                <h1>XML Sitemap</h1>

                <p>
                    This is an XML Sitemap, meant for consumption by search engines.<br/>
                    You can find more information about XML sitemaps on
                    <a href="https://www.sitemaps.org/" rel="noopener">sitemaps.org</a>.
                </p>

                <xsl:choose>
                    <xsl:when test="sm:sitemapindex">
                        <p>
                            This XML Sitemap Index file contains
                            <strong><xsl:value-of select="count(sm:sitemapindex/sm:sitemap)"/></strong>
                            sitemaps.
                        </p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Sitemap</th>
                                    <th>Last Modified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <xsl:for-each select="sm:sitemapindex/sm:sitemap">
                                    <tr>
                                        <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
                                        <td><xsl:value-of select="sm:lastmod"/></td>
                                    </tr>
                                </xsl:for-each>
                            </tbody>
                        </table>
                    </xsl:when>

                    <xsl:when test="sm:urlset">
                        <p>
                            This XML Sitemap contains
                            <strong><xsl:value-of select="count(sm:urlset/sm:url)"/></strong>
                            URLs.
                        </p>
                        <table>
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Last Modified</th>
                                    <th>Change Frequency</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                <xsl:for-each select="sm:urlset/sm:url">
                                    <tr>
                                        <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
                                        <td><xsl:value-of select="sm:lastmod"/></td>
                                        <td><xsl:value-of select="sm:changefreq"/></td>
                                        <td><xsl:value-of select="sm:priority"/></td>
                                    </tr>
                                </xsl:for-each>
                            </tbody>
                        </table>
                    </xsl:when>

                    <xsl:otherwise>
                        <p>This sitemap contains no entries.</p>
                    </xsl:otherwise>
                </xsl:choose>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
XSL;
    }
}
