<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ArtInpaFrontNewsThemeStandaloneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file($this->moduleRoot().'/module.json')) {
            self::markTestSkipped('The optional frontend news theme is not installed.');
        }
    }

    public function test_manifest_declares_every_theme_owned_platform_page(): void
    {
        $manifest = json_decode(
            (string) file_get_contents($this->moduleRoot().'/module.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            [
                'home',
                'news',
                'about',
                'art-inpa-news-home',
                'magazine-dynamic',
                'privacy-policy',
                'terms-of-service',
            ],
            data_get($manifest, 'frontend.owned_pages'),
        );
    }

    public function test_localized_committee_images_are_valid_and_complete(): void
    {
        $images = glob($this->moduleRoot().'/resources/assets/images/about/*') ?: [];

        self::assertCount(21, $images);

        foreach ($images as $image) {
            self::assertNotFalse(getimagesize($image), $image);
        }
    }

    public function test_theme_uses_platform_editorial_contract_instead_of_blog_internals(): void
    {
        $renderer = (string) file_get_contents($this->moduleRoot().'/src/Support/NewsThemeDynamicRenderer.php');

        self::assertStringContainsString('EditorialContentProvider', $renderer);
        self::assertStringNotContainsString('Modules\\Blog', $renderer);
        self::assertStringNotContainsString("Schema::hasTable('blog_", $renderer);
        self::assertStringNotContainsString('Post::query()', $renderer);
        self::assertStringNotContainsString('Category::query()', $renderer);
        self::assertStringNotContainsString('Tag::query()', $renderer);
    }

    private function moduleRoot(): string
    {
        return dirname(__DIR__, 2).'/modules/art-inpa-front-news-theme';
    }
}
