<?php

namespace Modules\Seo\Services;

/**
 * Extracts a small, language-agnostic set of repeated content words.
 *
 * This is intentionally an editorial signal only. It does not perform
 * keyword research, competition analysis, or ranking tracking.
 */
final class SeoKeywordExtractor
{
    /** @var array<string, true> */
    private const STOPWORDS = [
        'the' => true, 'and' => true, 'for' => true, 'with' => true, 'from' => true,
        'that' => true, 'this' => true, 'these' => true, 'those' => true, 'into' => true,
        'your' => true, 'you' => true, 'our' => true, 'are' => true, 'was' => true,
        'were' => true, 'will' => true, 'have' => true, 'has' => true, 'had' => true,
        'not' => true, 'but' => true, 'all' => true, 'can' => true, 'its' => true,
        'من' => true, 'في' => true, 'على' => true, 'إلى' => true, 'الى' => true,
        'عن' => true, 'مع' => true, 'هذا' => true, 'هذه' => true, 'ذلك' => true,
        'التي' => true, 'الذي' => true, 'الذين' => true, 'أن' => true, 'ان' => true,
        'أو' => true, 'او' => true, 'و' => true, 'ثم' => true, 'كل' => true,
        'هو' => true, 'هي' => true, 'هم' => true, 'كان' => true, 'كانت' => true,
        'يكون' => true, 'تكون' => true, 'لا' => true, 'ما' => true, 'كما' => true,
        'بعد' => true, 'قبل' => true, 'بين' => true, 'عند' => true, 'حتى' => true,
    ];

    /**
     * @return array<int, string>
     */
    public function extract(?string $text, int $limit = 3): array
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', mb_strtolower($text), $matches);

        $counts = [];

        foreach ($matches[0] ?? [] as $word) {
            $word = trim((string) $word, "'’\-");

            if (mb_strlen($word) < 3 || isset(self::STOPWORDS[$word])) {
                continue;
            }

            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        uksort($counts, static fn (string $a, string $b): int => strcmp($a, $b));
        uasort($counts, static fn (int $a, int $b): int => $b <=> $a);

        return array_slice(array_keys($counts), 0, max(1, $limit));
    }
}
