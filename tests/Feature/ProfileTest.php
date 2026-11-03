<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_updates_when_optional_phone_is_omitted(): void
    {
        $user = User::factory()->create(['phone' => '+8801711000001']);

        $this->actingAs($user)->put('/profile', ['name' => 'New Name', 'email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('+8801711000001', $user->phone);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_changing_email_resets_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'email' => 'changed@example.com']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name, 'email' => $user->email,
            'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name, 'email' => $user->email, 'current_password' => 'password',
            'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
    }
}
