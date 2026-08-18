<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertNull(User::where('email', 'test@example.com')->firstOrFail()->email_verified_at);
    }

    public function test_new_users_are_activated_immediately_when_email_verification_is_disabled(): void
    {
        app(SettingsRepository::class)->update([
            'general' => ['email_verification_required' => false],
        ]);

        $response = $this->post('/register', [
            'first_name' => 'Ready',
            'last_name' => 'User',
            'email' => 'ready@example.com',
            'phone' => '+962 79 123 4567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $user = User::where('email', 'ready@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('+962 79 123 4567', $user->phone);
        $response->assertRedirect(route('front.account', absolute: false));
    }
}
