<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminUserPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_paginated_fifty_per_page_and_filter_by_phone(): void
    {
        User::factory()->count(55)->create();
        $target = User::factory()->create([
            'name' => 'Search Target',
            'email' => 'phone-target@example.test',
            'phone' => '+962791234567',
        ]);

        $view = app(UserController::class)->index(Request::create('/admin/users', 'GET'));
        $users = $view->getData()['users'];
        $this->assertSame(50, $users->perPage());
        $this->assertGreaterThan(50, $users->total());

        $filteredView = app(UserController::class)->index(Request::create('/admin/users?q=791234567', 'GET'));
        $filtered = $filteredView->getData()['users'];
        $this->assertSame(1, $filtered->total());
        $this->assertSame($target->id, $filtered->first()->id);
    }
}
