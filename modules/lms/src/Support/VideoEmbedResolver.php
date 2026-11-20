<?php

namespace Modules\Lms\Support;

final class VideoEmbedResolver
{
    /**
     * @return array{provider: string, token: string, embed_url: string}|null
     */
    public static function resolve(string $url, bool $autoplay = false, bool $controlled = false): ?array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($url === '' || ! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $path = trim(rawurldecode((string) ($parts['path'] ?? '')), '/');

        if (self::hostMatches($host, 'youtu.be')) {
            $videoId = explode('/', $path)[0] ?? '';

            return self::youtube($videoId, $autoplay);
        }

        if (self::hostMatches($host, 'youtube.com') || self::hostMatches($host, 'youtube-nocookie.com')) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $videoId = trim((string) ($query['v'] ?? ''));

            if ($videoId === '' && preg_match('~^(?:embed|shorts|live)/([^/]+)~i', $path, $matches)) {
                $videoId = $matches[1];
            }

            return self::youtube($videoId, $autoplay);
        }

        if (self::hostMatches($host, 'vimeo.com') && preg_match('~(?:^|/)(\d+)(?:$|/)~', $path, $matches)) {
            $videoId = $matches[1];
            $query = $autoplay ? '?autoplay=1' : '';

            return [
                'provider' => 'vimeo',
                'token' => $videoId,
                'embed_url' => 'https://player.vimeo.com/video/'.$videoId.$query,
            ];
        }

        $peerTubeId = self::peerTubeVideoId($path);

        if ($peerTubeId !== null) {
            $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
            $originHost = str_contains($host, ':') ? '['.$host.']' : $host;
            $query = array_filter([
                'api' => $controlled ? 1 : null,
                'autoplay' => $autoplay ? 1 : null,
                'controls' => $controlled ? 0 : 1,
                'controlBar' => $controlled ? 0 : 1,
                'title' => 0,
                'warningTitle' => 0,
                'peertubeLink' => 0,
            ], static fn ($value): bool => $value !== null);

            return [
                'provider' => 'peertube',
                'token' => $peerTubeId,
                'embed_url' => $scheme.'://'.$originHost.$port.'/videos/embed/'.rawurlencode($peerTubeId).'?'.http_build_query($query),
            ];
        }

        return null;
    }

    public static function embedUrl(string $url, bool $autoplay = false, bool $controlled = false): ?string
    {
        return self::resolve($url, $autoplay, $controlled)['embed_url'] ?? null;
    }

    /** @return array{provider: string, token: string, embed_url: string}|null */
    private static function youtube(string $videoId, bool $autoplay): ?array
    {
        $videoId = trim($videoId);

        if (! preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
            return null;
        }

        $query = $autoplay ? '?autoplay=1&rel=0' : '?rel=0';

        return [
            'provider' => 'youtube',
            'token' => $videoId,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/'.rawurlencode($videoId).$query,
        ];
    }

    private static function peerTubeVideoId(string $path): ?string
    {
        if (! preg_match('~^(?:w|videos/(?:watch|embed))/([^/]+)/?$~i', $path, $matches)) {
            return null;
        }

        $videoId = trim($matches[1]);

        return preg_match('/^[A-Za-z0-9_-]{6,64}$/', $videoId) ? $videoId : null;
    }

    private static function hostMatches(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
