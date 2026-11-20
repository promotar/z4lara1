<?php

namespace Modules\Store\Application\CatalogTransfer;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Store\Domain\Media\MediaUploadCandidate;
use RuntimeException;

final class RemoteProductImageDownloader
{
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function download(string $url, ?string $expectedChecksum = null): DownloadedProductImage
    {
        $maximumBytes = (int) config('store.media.maximum_upload_bytes', 15_728_640);
        $maximumRedirects = (int) config('store.catalog_transfer.remote_image_maximum_redirects', 3);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'z4-store-import-');
        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary product image could not be prepared.');
        }

        try {
            $currentUrl = trim($url);
            for ($redirect = 0; $redirect <= $maximumRedirects; $redirect++) {
                $resolvedIp = $this->assertPublicHttpUrl($currentUrl);
                $options = [
                    'sink' => $temporaryPath,
                    'allow_redirects' => false,
                    'on_headers' => static function ($response) use ($maximumBytes): void {
                        $length = (int) $response->getHeaderLine('Content-Length');
                        if ($length > $maximumBytes) {
                            throw new RuntimeException('The remote product image exceeds the approved file size.');
                        }
                    },
                    'progress' => static function (int $downloadTotal, int $downloaded) use ($maximumBytes): void {
                        if ($downloadTotal > $maximumBytes || $downloaded > $maximumBytes) {
                            throw new RuntimeException('The remote product image exceeds the approved file size.');
                        }
                    },
                ];
                if (defined('CURLOPT_RESOLVE')) {
                    $parts = parse_url($currentUrl);
                    $host = (string) ($parts['host'] ?? '');
                    $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80));
                    $pinnedIp = str_contains($resolvedIp, ':') ? '['.$resolvedIp.']' : $resolvedIp;
                    $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"]];
                }

                $response = Http::connectTimeout((int) config('store.catalog_transfer.remote_image_connect_timeout', 5))
                    ->timeout((int) config('store.catalog_transfer.remote_image_timeout', 30))
                    ->withHeaders(['Accept' => 'image/jpeg,image/png,image/webp'])
                    ->withOptions($options)
                    ->get($currentUrl);

                if ($response->redirect()) {
                    if ($redirect === $maximumRedirects) {
                        throw new InvalidArgumentException('The remote product image exceeded the redirect limit.');
                    }
                    $location = trim((string) $response->header('Location'));
                    if ($location === '') {
                        throw new InvalidArgumentException('The remote product image returned an invalid redirect.');
                    }
                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
                    continue;
                }
                if (! $response->successful()) {
                    throw new InvalidArgumentException('The remote product image could not be downloaded (HTTP '.$response->status().').');
                }
                break;
            }

            $size = filesize($temporaryPath);
            if ($size === false || $size <= 0 || $size > $maximumBytes) {
                throw new InvalidArgumentException('The remote product image size is outside the approved range.');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) ?: '';
            $extension = self::EXTENSION_BY_MIME[$mime] ?? null;
            $dimensions = @getimagesize($temporaryPath);
            if ($extension === null || $dimensions === false) {
                throw new InvalidArgumentException('The remote file is not a valid JPEG, PNG, or WebP image.');
            }
            $checksum = hash_file('sha256', $temporaryPath);
            if ($checksum === false) {
                throw new RuntimeException('The downloaded product image checksum could not be calculated.');
            }
            if (filled($expectedChecksum) && ! hash_equals(strtolower(trim((string) $expectedChecksum)), $checksum)) {
                throw new InvalidArgumentException('The downloaded product image does not match the exported checksum.');
            }

            return new DownloadedProductImage(
                $temporaryPath,
                new MediaUploadCandidate('catalog-import-'.$checksum.'.'.$extension, $mime, (int) $size, (int) $dimensions[0], (int) $dimensions[1]),
                $checksum,
            );
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);
            throw $exception;
        }
    }

    private function assertPublicHttpUrl(string $url): string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('A product image URL is invalid.');
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Only public HTTP or HTTPS product image URLs are allowed.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new InvalidArgumentException('The product image host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('Product images cannot be imported from private or reserved network addresses.');
            }
        }

        return $ips[0];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique(array_filter($ips, static fn ($ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false)));
    }
}
