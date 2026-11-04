<?php

namespace Tests\Feature\Release;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reset_password_with_emailed_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'rahim@example.com']);

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'rahim@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=rahim@example.com")->assertOk();
        $this->post('/reset-password', ['token' => $token, 'email' => 'rahim@example.com', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])
            ->assertRedirect('/login');

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
        $this->post('/login', ['login' => 'rahim@example.com', 'password' => 'new-secret-123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_email_gets_the_same_answer_and_bad_token_fails(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'karim@example.com']);

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();
        Notification::assertNothingSent();

        $this->post('/reset-password', ['token' => 'forged', 'email' => 'karim@example.com', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])
            ->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('new-secret-123', $user->fresh()->password));
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/forgot-password', ['email' => "x{$i}@example.com"]);
        }

        $this->post('/forgot-password', ['email' => 'x6@example.com'])->assertStatus(429);
    }
}
