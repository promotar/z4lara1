<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultSuperAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_bootstraps_default_super_admin(): void
    {
        $user = User::query()
            ->where('email', 'ziad.mansor@gmail.com')
            ->first();

        $this->assertNotNull($user);
        $this->assertSame('Ziad Mansor', $user->name);
        $this->assertTrue(Hash::check('ziad.mansor', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue(Role::query()->where('name', 'super-admin')->exists());
    }
}
