<?php

namespace Tests\Feature\Tenancy;

use App\Models\Mess;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantAndMessManagementTest extends TestCase
{
    use RefreshDatabase;

    private function messPayload(string $name): array
    {
        return [
            'name' => $name,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'default_period_mode' => 'calendar',
            'default_meal_tracking_mode' => 'individual',
            'join_as_member' => '1',
        ];
    }

    public function test_user_can_create_tenant_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Dhaka Messes',
            'default_currency' => 'BDT',
            'default_timezone' => 'Asia/Dhaka',
        ])->assertRedirect();

        $tenant = Tenant::where('name', 'Dhaka Messes')->firstOrFail();
        $this->assertSame($user->id, $tenant->owner_id);
        $this->assertTrue($user->belongsToTenant($tenant));
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.created', 'tenant_id' => $tenant->id]);
    }

    public function test_tenant_owner_can_create_multiple_messes(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $tenant->owner;

        $this->actingAs($owner)->post("/t/{$tenant->slug}/messes", $this->messPayload('North Hall'))->assertRedirect();
        $this->actingAs($owner)->post("/t/{$tenant->slug}/messes", $this->messPayload('South Hall'))->assertRedirect();
        $this->actingAs($owner)->post("/t/{$tenant->slug}/messes", $this->messPayload('North Hall'))->assertRedirect();

        $this->assertSame(3, $tenant->messes()->count());
        $this->assertSame(3, $tenant->messes()->distinct()->count('slug'));

        $mess = $tenant->messes()->first();
        $this->assertTrue($owner->activeMembershipIn($mess)->roles->contains('slug', Role::MESS_ADMIN));
        $this->actingAs($owner)->get("/t/{$tenant->slug}/m/{$mess->slug}")->assertOk()->assertSee($mess->name);
    }

    public function test_non_owner_cannot_create_mess(): void
    {
        $mess = Mess::factory()->create();
        $admin = $this->memberOf($mess, Role::MESS_ADMIN);

        $this->actingAs($admin->user)
            ->post("/t/{$mess->tenant->slug}/messes", $this->messPayload('Rogue'))
            ->assertForbidden();
    }

    public function test_user_can_join_multiple_messes_with_different_roles(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create(['tenant_id' => $messA->tenant_id]);
        $messC = Mess::factory()->create();
        $owner = $messA->tenant->owner;
        $user = User::factory()->create(['email' => 'joiner@example.com']);

        $managerRole = Role::where('slug', Role::MANAGER)->value('id');
        $memberRole = Role::where('slug', Role::MEMBER)->value('id');

        $this->actingAs($owner)->post("/t/{$messA->tenant->slug}/m/{$messA->slug}/members", [
            'login' => 'joiner@example.com', 'joined_at' => now()->toDateString(), 'roles' => [$managerRole],
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("/t/{$messB->tenant->slug}/m/{$messB->slug}/members", [
            'login' => 'joiner@example.com', 'joined_at' => now()->toDateString(), 'roles' => [$memberRole],
        ])->assertSessionHasNoErrors();
        $this->actingAs($messC->tenant->owner)->post("/t/{$messC->tenant->slug}/m/{$messC->slug}/members", [
            'login' => 'joiner@example.com', 'joined_at' => now()->toDateString(), 'roles' => [$managerRole],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $user->memberships()->count());
        $this->assertTrue($user->can('manageMembers', $messA));
        $this->assertFalse($user->can('manageMembers', $messB));
        $this->assertTrue($user->can('view', $messB));
        $this->assertTrue($user->can('manageMembers', $messC));
        $this->assertSame(2, $user->accessibleTenants()->count());
    }

    public function test_member_codes_are_unique_and_sequential_per_mess(): void
    {
        $mess = Mess::factory()->create();
        $owner = $mess->tenant->owner;
        $memberRole = Role::where('slug', Role::MEMBER)->value('id');

        foreach (['a@example.com', 'b@example.com'] as $email) {
            User::factory()->create(['email' => $email]);
            $this->actingAs($owner)->post("/t/{$mess->tenant->slug}/m/{$mess->slug}/members", [
                'login' => $email, 'joined_at' => now()->toDateString(), 'roles' => [$memberRole],
            ]);
        }

        $this->assertSame(['M0001', 'M0002'], $mess->members()->orderBy('member_code')->pluck('member_code')->all());
    }

    public function test_user_cannot_have_two_active_memberships_in_the_same_mess(): void
    {
        $mess = Mess::factory()->create();
        $existing = $this->memberOf($mess, Role::MEMBER);
        $memberRole = Role::where('slug', Role::MEMBER)->value('id');

        $this->actingAs($mess->tenant->owner)->post("/t/{$mess->tenant->slug}/m/{$mess->slug}/members", [
            'login' => $existing->user->email, 'joined_at' => now()->toDateString(), 'roles' => [$memberRole],
        ])->assertSessionHasErrors('user');

        // Database-level guard as well.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('mess_members')->insert([
            'tenant_id' => $mess->tenant_id, 'mess_id' => $mess->id, 'user_id' => $existing->user_id,
            'member_code' => 'DUP1', 'full_name' => 'Dup', 'joined_at' => now()->toDateString(), 'membership_status' => 'active',
        ]);
    }

    public function test_manager_can_add_members_but_only_with_member_role(): void
    {
        $mess = Mess::factory()->create();
        $manager = $this->memberOf($mess, Role::MANAGER);
        User::factory()->create(['email' => 'new@example.com']);
        $adminRole = Role::where('slug', Role::MESS_ADMIN)->value('id');

        $this->actingAs($manager->user)->post("/t/{$mess->tenant->slug}/m/{$mess->slug}/members", [
            'login' => 'new@example.com', 'joined_at' => now()->toDateString(), 'roles' => [$adminRole],
        ])->assertSessionHasErrors('roles');

        $this->assertSame(1, $mess->members()->count());
    }

    public function test_mess_admin_can_change_member_roles(): void
    {
        $mess = Mess::factory()->create();
        $admin = $this->memberOf($mess, Role::MESS_ADMIN);
        $member = $this->memberOf($mess, Role::MEMBER);
        $accountant = Role::where('slug', Role::ACCOUNTANT)->value('id');

        $this->actingAs($admin->user)
            ->put("/t/{$mess->tenant->slug}/m/{$mess->slug}/members/{$member->id}/roles", ['roles' => [$accountant]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$accountant], $member->roles()->pluck('roles.id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.roles_synced', 'auditable_id' => $member->id]);
    }

    public function test_tenant_specific_role_from_another_tenant_cannot_be_assigned(): void
    {
        $mess = Mess::factory()->create();
        $member = $this->memberOf($mess, Role::MEMBER);
        $foreignRole = Role::create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Foreign', 'slug' => 'foreign', 'scope' => 'mess']);

        $this->actingAs($mess->tenant->owner)
            ->put("/t/{$mess->tenant->slug}/m/{$mess->slug}/members/{$member->id}/roles", ['roles' => [$foreignRole->id]])
            ->assertSessionHasErrors('roles');
    }

    public function test_mess_settings_can_be_updated_by_admin_only(): void
    {
        $mess = Mess::factory()->create();
        $admin = $this->memberOf($mess, Role::MESS_ADMIN);
        $member = $this->memberOf($mess, Role::MEMBER);
        $payload = ['name' => 'Renamed', 'status' => 'active'] + $this->messPayload('x');
        unset($payload['join_as_member']);
        $payload['name'] = 'Renamed';
        $url = "/t/{$mess->tenant->slug}/m/{$mess->slug}/settings";

        $this->actingAs($member->user)->put($url, $payload)->assertForbidden();
        $this->actingAs($admin->user)->put($url, $payload)->assertRedirect();

        $this->assertSame('Renamed', $mess->fresh()->name);
    }

    public function test_home_lists_only_accessible_messes(): void
    {
        $messA = Mess::factory()->create(['name' => 'Visible Mess']);
        $messB = Mess::factory()->create(['tenant_id' => $messA->tenant_id, 'name' => 'Hidden Mess']);
        $member = $this->memberOf($messA, Role::MEMBER);

        $this->actingAs($member->user)->get('/')
            ->assertOk()
            ->assertSee('Visible Mess')
            ->assertDontSee('Hidden Mess');
    }
}
