<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Seo\DefaultSitemapProvider;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Models\SeoSitemapRun;
use SimpleXMLElement;
use Throwable;

final class AuditUrlProvider
{
    public function __construct(
        private readonly DefaultSitemapProvider $core,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function urls(int $siteId, int $limit): array
    {
        $limit = max(1, $limit);
        $urls = [];

        try {
            $run = SeoSitemapRun::query()
                ->where('site_id', $siteId)
                ->where('status', SeoSitemapRun::STATUS_COMPLETED)
                ->latest('id')
                ->first();
        } catch (Throwable $exception) {
            report($exception);
            $run = null;
        }

        if ($run !== null) {
            foreach (SeoSitemapChunk::query()
                ->where('site_id', $siteId)
                ->where('run_id', $run->id)
                ->orderBy('chunk_index')
                ->cursor() as $chunk) {
                $xml = simplexml_load_string((string) $chunk->content);

                if (! $xml instanceof SimpleXMLElement) {
                    continue;
                }

                foreach ($xml->xpath('//*[local-name()="url"]/*[local-name()="loc"]') ?: [] as $node) {
                    $url = trim((string) $node);

                    if ($url !== '' && ! isset($urls[$url])) {
                        $urls[$url] = true;
                    }
                }
            }
        } else {
            foreach ($this->core->entries() as $entry) {
                $url = trim((string) ($entry['loc'] ?? ''));

                if ($url !== '' && ! isset($urls[$url])) {
                    $urls[$url] = true;
                }
            }
        }

        return array_slice(array_keys($urls), 0, $limit);
    }
}
