<?php

namespace Modules\Blog\Support;

/**
 * Legacy posts store media as WordPress-style shortcodes:
 *   [embed]https://youtu.be/VIDEO[/embed]
 * They must render as a responsive, privacy-friendly player instead of raw
 * text. Unknown links degrade to a normal external link.
 */
final class ContentEmbeds
{
    public static function render(?string $html): string
    {
        $html = (string) $html;

        if ($html === '' || ! str_contains($html, '[embed]')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '#\[embed\](.*?)\[/embed\]#is',
            static function (array $match): string {
                $url = trim(html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $id = self::youtubeId($url);

                if ($id === null) {
                    $safe = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    return '<p><a href="'.$safe.'" target="_blank" rel="noopener noreferrer">'.$safe.'</a></p>';
                }

                return '<div class="blog-embed">'
                    .'<iframe src="https://www.youtube-nocookie.com/embed/'.$id.'"'
                    .' title="YouTube video" loading="lazy"'
                    .' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
                    .' allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>'
                    .'</div>';
            },
            $html,
        );
    }

    /**
     * Plain-text version for meta tags: shortcodes removed, whitespace collapsed.
     */
    public static function plain(?string $text): string
    {
        $text = (string) $text;
        $text = (string) preg_replace('#\[embed\].*?\[/embed\]#is', '', $text);
        $text = (string) preg_replace('#\s+#u', ' ', $text);

        return trim($text);
    }

    private static function youtubeId(string $url): ?string
    {
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{6,})~i',
            '~youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)([A-Za-z0-9_-]{6,})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }
}
