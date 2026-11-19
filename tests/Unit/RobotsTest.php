<?php

namespace Tests\Unit;

use App\Platform\Core\Seo\Robots;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_merge_keeps_the_most_restrictive_directives(): void
    {
        $base = new Robots(index: true, follow: true);
        $restricted = new Robots(
            index: false,
            follow: false,
            maxSnippet: 20,
            maxImagePreview: 'standard',
            maxVideoPreview: 10,
            directives: ['noarchive'],
        );

        $merged = $base->merge($restricted);

        $this->assertFalse($merged->index);
        $this->assertFalse($merged->follow);
        $this->assertSame(20, $merged->maxSnippet);
        $this->assertSame('standard', $merged->maxImagePreview);
        $this->assertSame(10, $merged->maxVideoPreview);
        $this->assertContains('noarchive', $merged->directives);
        $this->assertStringContainsString('noindex', $merged->toMeta());
        $this->assertStringContainsString('nofollow', $merged->toMeta());
    }

    public function test_noindex_factory_is_not_indexable(): void
    {
        $robots = Robots::noindex();

        $this->assertFalse($robots->isIndexable());
        $this->assertStringContainsString('noindex, follow', $robots->toMeta());
    }

    public function test_null_limits_do_not_override_existing_limits(): void
    {
        $merged = (new Robots(maxSnippet: 40))->merge(new Robots());

        $this->assertSame(40, $merged->maxSnippet);
    }
}
