<?php

namespace Tests\Feature\Meals;

use App\Models\MessInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_without_account_can_be_created_and_linked_later(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        $owner = $mess->tenant->owner;
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}/members";
        $memberRole = Role::where('slug', Role::MEMBER)->value('id');

        $this->actingAs($owner)->post("{$base}/without-account", [
            'full_name' => 'Karim Cook', 'joined_at' => now()->toDateString(), 'roles' => [$memberRole],
        ])->assertSessionHasNoErrors();

        $member = $mess->members()->where('full_name', 'Karim Cook')->firstOrFail();
        $this->assertNull($member->user_id);

        $user = User::factory()->create(['phone' => '+8801712345678']);
        $this->actingAs($owner)->put("{$base}/{$member->id}/link", ['login' => '+8801712345678'])->assertSessionHasNoErrors();

        $this->assertSame($user->id, $member->fresh()->user_id);
        $this->assertTrue($user->can('view', $mess));
    }

    public function test_invitation_flow_requires_matching_recipient(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        $owner = $mess->tenant->owner;
        $managerRole = Role::where('slug', Role::MANAGER)->value('id');

        $response = $this->actingAs($owner)->post("/t/{$mess->tenant->slug}/m/{$mess->slug}/members/invitations", [
            'email' => 'invitee@example.com', 'role_id' => $managerRole,
        ])->assertSessionHasNoErrors();

        $link = session('invitation_link');
        $this->assertNotNull($link);
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->assertNotSame($token, MessInvitation::first()->invitation_token_hash);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get("/invitations/{$token}")->assertOk()->assertSee($mess->name);
        $this->actingAs($stranger)->post("/invitations/{$token}")->assertSessionHasErrors('invitation');

        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $this->actingAs($invitee)->post("/invitations/{$token}")->assertRedirect();

        $this->assertTrue($invitee->can('manageMembers', $mess));
        $this->assertSame('accepted', MessInvitation::first()->status);

        // Single use.
        $this->actingAs($invitee)->post("/invitations/{$token}")->assertSessionHasErrors('invitation');
        $this->actingAs($invitee)->get('/invitations/not-a-real-token')->assertNotFound();
    }

    public function test_manager_cannot_invite_with_elevated_role(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        $manager = $this->memberOf($mess, Role::MANAGER);

        $this->actingAs($manager->user)->post("/t/{$mess->tenant->slug}/m/{$mess->slug}/members/invitations", [
            'email' => 'x@example.com', 'role_id' => Role::where('slug', Role::MESS_ADMIN)->value('id'),
        ])->assertSessionHasErrors('role_id');
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        $user = User::factory()->create(['email' => 'late@example.com']);
        $token = 'expired-token-value';
        $mess->invitations()->create([
            'tenant_id' => $mess->tenant_id, 'email' => 'late@example.com', 'invitation_token_hash' => hash('sha256', $token),
            'role_id' => Role::where('slug', Role::MEMBER)->value('id'), 'expires_at' => now()->subDay(), 'status' => 'pending',
        ]);

        $this->actingAs($user)->post("/invitations/{$token}")->assertSessionHasErrors('invitation');
        $this->assertFalse($user->can('view', $mess));
    }

    public function test_member_status_changes_and_joining_date_guard(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MESS_ADMIN);
        $owner = $mess->tenant->owner;
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}/members/{$member->id}";

        $this->actingAs($owner)->put("{$base}/status", ['status' => 'left'])->assertSessionHasErrors('left_at');
        $this->actingAs($owner)->put("{$base}/status", ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->actingAs($member->user)->get("/t/{$mess->tenant->slug}/m/{$mess->slug}")->assertNotFound();

        $this->actingAs($owner)->put("{$base}/status", ['status' => 'active'])->assertSessionHasNoErrors();
        $this->actingAs($member->user)->get("/t/{$mess->tenant->slug}/m/{$mess->slug}")->assertOk();
    }
}
