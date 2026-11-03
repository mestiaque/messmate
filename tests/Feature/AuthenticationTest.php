<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_user_can_register_with_email(): void
    {
        $this->post('/register', [
            'name' => 'Rahim',
            'email' => 'rahim@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'rahim@example.com', 'is_platform_admin' => false]);
    }

    public function test_user_can_register_with_phone_only(): void
    {
        $this->post('/register', [
            'name' => 'Karim',
            'phone' => '+8801711000000',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['phone' => '+8801711000000', 'email' => null]);
    }

    public function test_registration_cannot_grant_platform_admin(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'is_platform_admin' => 1,
        ]);

        $this->assertFalse(User::where('email', 'sneaky@example.com')->first()->is_platform_admin);
    }

    public function test_user_can_log_in_with_email_or_phone(): void
    {
        $user = User::factory()->create(['phone' => '+8801811000000']);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => '+8801811000000', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_log_in_or_keep_session(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
