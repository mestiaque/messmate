<?php

namespace Tests\Feature\Billing;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_pages_require_platform_admin(): void
    {
        $tenant = Tenant::factory()->create();

        foreach (['/platform/plans', '/platform/payments', '/platform/settings', '/platform/tickets', '/platform/announcements'] as $path) {
            $this->actingAs($tenant->owner)->get($path)->assertForbidden();
        }
        $this->actingAs($tenant->owner)->post('/platform/plans', ['name' => 'Hack'])->assertForbidden();
    }

    public function test_admin_manages_plans_settings_and_tenant_subscription(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $tenant = Tenant::factory()->create();

        foreach (['/platform/plans', '/platform/payments', '/platform/settings', '/platform/tickets', '/platform/announcements', "/platform/tenants/{$tenant->id}"] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        $this->actingAs($admin)->post('/platform/plans', ['name' => 'Student', 'slug' => 'student', 'monthly_price' => '99', 'yearly_price' => '990', 'trial_days' => '7', 'max_messes' => '1', 'features' => ['reports_export'], 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['reports_export'], SubscriptionPlan::where('slug', 'student')->first()->features);

        $this->actingAs($admin)->put('/platform/settings', ['grace_days' => '3', 'default_trial_plan' => 'student', 'payment_instructions' => 'bKash 017'])->assertSessionHasNoErrors();
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/organizations', ['name' => 'Student Org', 'default_currency' => 'BDT', 'default_timezone' => 'Asia/Dhaka']);
        $this->assertSame('student', Tenant::where('name', 'Student Org')->first()->subscription->plan->slug);

        $this->actingAs($admin)->put("/platform/tenants/{$tenant->id}/subscription", ['plan_id' => SubscriptionPlan::where('slug', 'pro')->value('id'), 'until' => now()->addYear()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('pro', $tenant->subscription->fresh()->plan->slug);
        $this->assertDatabaseHas('subscription_events', ['tenant_id' => $tenant->id, 'event' => 'admin_changed']);
    }

    public function test_support_ticket_round_trip_and_platform_announcement(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        $this->actingAs($tenant->owner)->post("/t/{$tenant->slug}/support", ['subject' => 'Cannot export', 'body' => 'CSV button missing'])->assertRedirect();
        $ticket = $tenant->supportTickets()->firstOrFail();

        $this->actingAs($other->owner)->get("/t/{$other->slug}/support/{$ticket->id}")->assertNotFound();
        $this->actingAs($admin)->post("/platform/tickets/{$ticket->id}/reply", ['body' => 'Upgrade to Basic', 'status' => 'answered'])->assertSessionHasNoErrors();
        $this->actingAs($tenant->owner)->get("/t/{$tenant->slug}/support/{$ticket->id}")->assertOk()->assertSee('Upgrade to Basic');
        $this->assertSame('answered', $ticket->fresh()->status);

        $this->actingAs($admin)->post('/platform/announcements', ['title' => 'Maintenance tonight', 'body' => '2am–3am'])->assertSessionHasNoErrors();
        $this->actingAs($tenant->owner)->get("/t/{$tenant->slug}")->assertSee('Maintenance tonight');
    }
}
