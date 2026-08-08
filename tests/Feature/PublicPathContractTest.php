<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPathContractTest extends TestCase
{
    public function test_public_files_resolve_inside_the_project_public_directory(): void
    {
        $expectedPublicRoot = base_path('public');

        $this->assertSame(
            $this->normalize($expectedPublicRoot),
            $this->normalize(public_path()),
        );
        $this->assertSame(
            $this->normalize($expectedPublicRoot.'/build/manifest.json'),
            $this->normalize(public_path('build/manifest.json')),
        );
        $this->assertStringNotContainsString('public_html', public_path());
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
