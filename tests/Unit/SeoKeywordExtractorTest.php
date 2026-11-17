<?php

namespace Tests\Unit;

use Modules\Seo\Services\SeoKeywordExtractor;
use Tests\TestCase;

class SeoKeywordExtractorTest extends TestCase
{
    public function test_it_returns_the_three_most_repeated_content_words(): void
    {
        $keywords = (new SeoKeywordExtractor())->extract(
            'Interior design ideas for interior spaces. Interior colors and interior materials.',
            3,
        );

        $this->assertSame('interior', $keywords[0]);
        $this->assertCount(3, $keywords);
    }

    public function test_it_ignores_stopwords_and_short_tokens(): void
    {
        $keywords = (new SeoKeywordExtractor())->extract('the and for a in من في على الرسم الرسم الألوان', 3);

        $this->assertSame(['الرسم', 'الألوان'], $keywords);
    }
}
