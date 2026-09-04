<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PlatformPaginationTest extends TestCase
{
    public function test_pagination_has_fast_navigation_and_preserves_filters(): void
    {
        $request = Request::create('/admin/records', 'GET', [
            'q' => 'artist',
            'filter' => ['status' => 'active'],
            'per_page' => 10,
            'items_page' => 6,
            'secondary_page' => 3,
        ]);
        $this->app->instance('request', $request);

        $paginator = new LengthAwarePaginator(
            range(51, 60),
            123,
            10,
            6,
            ['path' => '/admin/records', 'pageName' => 'items_page'],
        );
        $paginator->appends($request->query());

        $html = Blade::render(
            '<x-platform-pagination :paginator="$paginator" page-name="items_page" />',
            compact('paginator'),
        );

        $this->assertStringContainsString('aria-label="First page"', $html);
        $this->assertStringContainsString('aria-label="Last page"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('name="items_page"', $html);
        $this->assertStringContainsString('max="13"', $html);
        $this->assertStringContainsString('name="q" value="artist"', $html);
        $this->assertStringContainsString('name="filter[status]" value="active"', $html);
        $this->assertStringContainsString('name="secondary_page" value="3"', $html);
        $this->assertStringNotContainsString('type="hidden" name="items_page"', $html);
    }

    public function test_invalid_page_input_resolves_to_the_first_page(): void
    {
        $this->app->instance('request', Request::create('/admin/records', 'GET', [
            'items_page' => 'not-a-page',
        ]));

        $this->assertSame(1, Paginator::resolveCurrentPage('items_page'));
    }
}
