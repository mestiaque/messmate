<?php

namespace Tests\Feature\Release;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_creates_admin_and_is_safe_to_rerun(): void
    {
        $this->artisan('messmate:install', ['--admin-name' => 'Owner', '--admin-email' => 'root@example.com', '--admin-password' => 'Str0ng-pass!'])
            ->assertSuccessful();

        $admin = User::where('email', 'root@example.com')->firstOrFail();
        $this->assertTrue($admin->is_platform_admin);
        $this->assertTrue(SubscriptionPlan::where('slug', 'basic')->exists());

        // Re-running never changes an existing password.
        $this->artisan('messmate:install', ['--admin-email' => 'root@example.com', '--admin-password' => 'other-pass-1'])->assertSuccessful();
        $this->assertTrue(Hash::check('Str0ng-pass!', $admin->fresh()->password));
        $this->assertSame(1, User::where('email', 'root@example.com')->count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->artisan('messmate:install', ['--admin-name' => 'Owner', '--admin-email' => 'weak@example.com', '--admin-password' => '123'])
            ->assertFailed();
        $this->assertFalse(User::where('email', 'weak@example.com')->exists());
    }

    public function test_environment_check_passes_in_test_environment(): void
    {
        $this->artisan('messmate:check')->assertSuccessful();
    }
}
