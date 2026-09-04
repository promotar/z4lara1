<?php

namespace Tests\Unit;

use App\Platform\Core\Services\SafeReturnPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeReturnPathTest extends TestCase
{
    public function test_internal_path_and_query_string_are_preserved(): void
    {
        $this->assertSame('/courses/painting?lesson=4&tab=video', (new SafeReturnPath)->sanitize('/courses/painting?lesson=4&tab=video'));
    }

    #[DataProvider('unsafePaths')]
    public function test_external_or_authentication_paths_are_rejected(mixed $path): void
    {
        $this->assertNull((new SafeReturnPath)->sanitize($path));
    }

    public static function unsafePaths(): array
    {
        return [
            'external URL' => ['https://example.com/course'],
            'protocol relative' => ['//example.com/course'],
            'encoded protocol relative' => ['/%2Fexample.com/course'],
            'windows separator' => ['/\\example.com'],
            'traversal' => ['/courses/../admin'],
            'login loop' => ['/login?return_to=/courses'],
            'fragment' => ['/courses#lesson'],
            'empty' => [''],
            'non string' => [42],
        ];
    }
}
