<?php

namespace Modules\Seo\Services;

use Modules\Seo\Models\SeoAuditIssue;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Models\SeoSitemapRun;
use SimpleXMLElement;
use Throwable;

final class SitemapValidator
{
    public function __construct(
        private readonly SeoSiteResolver $sites,
        private readonly SitemapUrlPolicy $policy,
    ) {
    }

    /**
     * @return array{errors: int, warnings: int, urls: int, chunks: int, run_id: int|null}
     */
    public function validate(?int $siteId = null): array
    {
        $siteId ??= $this->sites->id();
        $run = SeoSitemapRun::query()
            ->where('site_id', $siteId)
            ->where('status', SeoSitemapRun::STATUS_COMPLETED)
            ->latest('id')
            ->first();

        SeoAuditIssue::query()
            ->where('site_id', $siteId)
            ->where('type', 'like', 'sitemap.%')
            ->delete();

        if ($run === null) {
            $this->issue($siteId, null, 'sitemap.missing', 'error', 'No completed sitemap run exists.');

            return ['errors' => 1, 'warnings' => 0, 'urls' => 0, 'chunks' => 0, 'run_id' => null];
        }

        $errors = 0;
        $warnings = 0;
        $totalUrls = 0;
        $totalChunks = 0;
        $seen = [];

        $chunks = SeoSitemapChunk::query()
            ->where('site_id', $siteId)
            ->where('run_id', $run->id)
            ->orderBy('chunk_index')
            ->get();

        foreach ($chunks as $chunk) {
            $totalChunks++;

            try {
                $previous = libxml_use_internal_errors(true);
                $xml = simplexml_load_string((string) $chunk->content);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            } catch (Throwable) {
                $xml = false;
            }

            if (! $xml instanceof SimpleXMLElement) {
                $errors++;
                $this->issue($siteId, $run->id, 'sitemap.xml', 'error', 'Chunk '.$chunk->chunk_index.' contains invalid XML.', $chunk->chunk_index);

                continue;
            }

            $nodes = $xml->xpath('//*[local-name()="url"]/*[local-name()="loc"]') ?: [];

            if (count($nodes) !== (int) $chunk->urls_count) {
                $warnings++;
                $this->issue($siteId, $run->id, 'sitemap.count', 'warning', 'Chunk '.$chunk->chunk_index.' URL count does not match metadata.', $chunk->chunk_index);
            }

            foreach ($nodes as $node) {
                $url = trim((string) $node);
                $totalUrls++;

                if ($url === '' || ! $this->policy->allows($url, $siteId)) {
                    $warnings++;
                    $this->issue($siteId, $run->id, 'sitemap.policy', 'warning', 'URL is not allowed by sitemap policy.', $chunk->chunk_index, $url);

                    continue;
                }

                if (isset($seen[$url])) {
                    $warnings++;
                    $this->issue($siteId, $run->id, 'sitemap.duplicate', 'warning', 'Duplicate sitemap URL.', $chunk->chunk_index, $url);

                    continue;
                }

                $seen[$url] = true;
            }
        }

        if ((int) $run->urls_count !== $totalUrls) {
            $warnings++;
            $this->issue($siteId, $run->id, 'sitemap.count', 'warning', 'Run URL count does not match chunk contents.');
        }

        if ((int) $run->chunks_count !== $totalChunks) {
            $warnings++;
            $this->issue($siteId, $run->id, 'sitemap.count', 'warning', 'Run chunk count does not match stored chunks.');
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'urls' => $totalUrls,
            'chunks' => $totalChunks,
            'run_id' => (int) $run->id,
        ];
    }

    private function issue(
        int $siteId,
        ?int $runId,
        string $type,
        string $severity,
        string $message,
        ?int $chunk = null,
        ?string $url = null,
    ): void {
        SeoAuditIssue::query()->create([
            'site_id' => $siteId,
            'module_slug' => 'seo',
            'url_hash' => $url ? hash('sha256', $url) : ($runId ? 'run:'.$runId : null),
            'url' => $url ?: ($runId ? 'sitemap-run:'.$runId : 'sitemap'),
            'type' => $type,
            'severity' => $severity,
            'status' => 'open',
            'message' => $message.($chunk !== null ? ' Chunk: '.$chunk.'.' : ''),
            'detected_at' => now(),
        ]);
    }
}
