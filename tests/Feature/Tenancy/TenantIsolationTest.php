<?php

namespace Tests\Feature\Tenancy;

use App\Models\Mess;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAs($tenantA->owner)->get("/t/{$tenantB->slug}")->assertNotFound();
        $this->actingAs($tenantA->owner)->get("/t/{$tenantB->slug}/settings")->assertNotFound();
        $this->actingAs($tenantA->owner)->put("/t/{$tenantB->slug}/settings", ['name' => 'Hacked'])->assertNotFound();
        $this->assertNotSame('Hacked', $tenantB->fresh()->name);
    }

    public function test_user_cannot_open_another_tenants_mess(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create();

        $this->actingAs($messA->tenant->owner)
            ->get("/t/{$messB->tenant->slug}/m/{$messB->slug}")
            ->assertNotFound();
    }

    public function test_foreign_mess_slug_under_own_tenant_prefix_is_not_resolved(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create(['slug' => 'unique-foreign-slug']);

        $this->actingAs($messA->tenant->owner)
            ->get("/t/{$messA->tenant->slug}/m/{$messB->slug}")
            ->assertNotFound();
    }

    public function test_member_cannot_open_other_mess_in_same_tenant(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create(['tenant_id' => $messA->tenant_id]);
        $member = $this->memberOf($messA, Role::MESS_ADMIN);

        $this->actingAs($member->user)->get("/t/{$messA->tenant->slug}/m/{$messA->slug}")->assertOk();
        $this->actingAs($member->user)->get("/t/{$messB->tenant->slug}/m/{$messB->slug}")->assertNotFound();
        $this->actingAs($member->user)->get("/t/{$messB->tenant->slug}/m/{$messB->slug}/members")->assertNotFound();
    }

    public function test_member_of_another_mess_cannot_be_modified_through_own_mess(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create(['tenant_id' => $messA->tenant_id]);
        $admin = $this->memberOf($messA, Role::MESS_ADMIN);
        $victim = $this->memberOf($messB, Role::MEMBER);
        $adminRole = Role::where('slug', Role::MESS_ADMIN)->value('id');

        $this->actingAs($admin->user)
            ->put("/t/{$messA->tenant->slug}/m/{$messA->slug}/members/{$victim->id}/roles", ['roles' => [$adminRole]])
            ->assertNotFound();

        $this->assertFalse($victim->roles()->where('slug', Role::MESS_ADMIN)->exists());
    }

    public function test_member_list_does_not_leak_other_messes(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create(['tenant_id' => $messA->tenant_id]);
        $admin = $this->memberOf($messA, Role::MESS_ADMIN);
        $other = $this->memberOf($messB, Role::MEMBER);

        $this->actingAs($admin->user)
            ->get("/t/{$messA->tenant->slug}/m/{$messA->slug}/members")
            ->assertOk()
            ->assertDontSee($other->full_name);
    }

    public function test_plain_member_cannot_view_member_list_or_settings(): void
    {
        $mess = Mess::factory()->create();
        $member = $this->memberOf($mess, Role::MEMBER);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        // Plain members land on their own portal instead of the admin dashboard.
        $this->actingAs($member->user)->get($base)->assertRedirect("{$base}/me");
        $this->actingAs($member->user)->get("{$base}/me")->assertOk();
        $this->actingAs($member->user)->get("{$base}/members")->assertForbidden();
        $this->actingAs($member->user)->get("{$base}/settings")->assertForbidden();
    }

    public function test_inactive_membership_loses_access(): void
    {
        $mess = Mess::factory()->create();
        $member = $this->memberOf($mess, Role::MESS_ADMIN);
        $member->forceFill(['membership_status' => 'left'])->save();

        $this->actingAs($member->user)->get("/t/{$mess->tenant->slug}/m/{$mess->slug}")->assertNotFound();
    }

    public function test_suspended_tenant_is_blocked(): void
    {
        $mess = Mess::factory()->create();
        $mess->tenant->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($mess->tenant->owner)->get("/t/{$mess->tenant->slug}")->assertForbidden();
        $this->actingAs($mess->tenant->owner)->get("/t/{$mess->tenant->slug}/m/{$mess->slug}")->assertForbidden();
    }

    public function test_platform_area_requires_platform_admin(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($tenant->owner)->get('/platform')->assertForbidden();
        $this->actingAs($tenant->owner)->post("/platform/tenants/{$tenant->id}/suspend")->assertForbidden();

        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->get('/platform')->assertOk();
        $this->actingAs($admin)->get('/platform/tenants')->assertOk()->assertSee($tenant->name);
        $this->actingAs($admin)->post("/platform/tenants/{$tenant->id}/suspend")->assertRedirect();
        $this->assertSame('suspended', $tenant->fresh()->status->value);
    }

    public function test_platform_admin_has_no_implicit_access_to_tenant_data(): void
    {
        $mess = Mess::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get("/t/{$mess->tenant->slug}/m/{$mess->slug}")->assertNotFound();
    }
}
