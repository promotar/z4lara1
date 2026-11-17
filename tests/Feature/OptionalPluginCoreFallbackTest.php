<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OptionalPluginCoreFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_core_flows_do_not_require_lms_routes(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('student', 'web');
        $user->assignRole('student');

        $this->actingAs($user)->get('/account')->assertOk();
        $this->actingAs($user)->get('/admin/plugins')->assertForbidden();

        auth()->logout();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
    }
}
