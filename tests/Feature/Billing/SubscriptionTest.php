<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Mess;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function messPayload(string $name): array
    {
        return ['name' => $name, 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'default_period_mode' => 'calendar', 'default_meal_tracking_mode' => 'individual', 'join_as_member' => '0'];
    }

    public function test_new_organization_starts_a_trial_of_the_default_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/organizations', ['name' => 'Fresh Org', 'default_currency' => 'BDT', 'default_timezone' => 'Asia/Dhaka']);

        $subscription = Tenant::where('name', 'Fresh Org')->firstOrFail()->subscription;
        $this->assertSame(SubscriptionStatus::Trial, $subscription->status);
        $this->assertSame('basic', $subscription->plan->slug);
        $this->assertTrue($subscription->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));
        $this->assertDatabaseHas('subscription_events', ['subscription_id' => $subscription->id, 'event' => 'trial_started']);
    }

    public function test_mess_member_and_user_limits_are_enforced_on_the_server(): void
    {
        $tenant = Tenant::factory()->onPlan('free')->create(); // 1 mess, 8 members, 10 users
        $owner = $tenant->owner;

        $this->actingAs($owner)->post("/t/{$tenant->slug}/messes", $this->messPayload('First'))->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("/t/{$tenant->slug}/messes", $this->messPayload('Second'))->assertSessionHasErrors('plan');
        $this->assertSame(1, $tenant->messes()->count());

        $mess = $tenant->messes()->first();
        $memberRole = Role::where('slug', Role::MEMBER)->value('id');
        for ($i = 1; $i <= 8; $i++) {
            $this->actingAs($owner)->post("/t/{$tenant->slug}/m/{$mess->slug}/members/without-account", ['full_name' => "Member {$i}", 'joined_at' => now()->toDateString(), 'roles' => [$memberRole]])->assertSessionHasNoErrors();
        }
        $this->actingAs($owner)->post("/t/{$tenant->slug}/m/{$mess->slug}/members/without-account", ['full_name' => 'Ninth', 'joined_at' => now()->toDateString(), 'roles' => [$memberRole]])
            ->assertSessionHasErrors('plan');
        $this->assertSame(8, $mess->members()->count());
    }

    public function test_user_limit_counts_organization_users(): void
    {
        $tenant = Tenant::factory()->onPlan('free')->create();
        SubscriptionPlan::where('slug', 'free')->update(['max_users' => 2, 'max_members_per_mess' => 50]);
        $mess = Mess::factory()->create(['tenant_id' => $tenant->id]);
        $memberRole = Role::where('slug', Role::MEMBER)->value('id');
        $url = "/t/{$tenant->slug}/m/{$mess->slug}/members";

        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($tenant->owner)->post($url, ['login' => $first->email, 'joined_at' => now()->toDateString(), 'roles' => [$memberRole]])->assertSessionHasNoErrors();
        $this->actingAs($tenant->owner)->post($url, ['login' => $second->email, 'joined_at' => now()->toDateString(), 'roles' => [$memberRole]])->assertSessionHasErrors('plan');
    }

    public function test_expired_trial_is_read_only_but_data_stays_and_reads_work(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDay()])->create();
        $mess = Mess::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kept Mess']);
        $base = "/t/{$tenant->slug}/m/{$mess->slug}";

        $this->actingAs($tenant->owner)->put("{$base}/settings", $this->messPayload('Renamed') + ['status' => 'active'])->assertSessionHasNoErrors();

        $this->travel(2)->days();
        $this->assertSame(SubscriptionService::READ_ONLY, app(SubscriptionService::class)->access($tenant->fresh()));

        $this->actingAs($tenant->owner)->put("{$base}/settings", $this->messPayload('Blocked') + ['status' => 'active'])->assertSessionHasErrors('subscription');
        $this->assertSame('Renamed', $mess->fresh()->name);

        $this->actingAs($tenant->owner)->get($base)->assertOk()->assertSee('read-only');
        $this->actingAs($tenant->owner)->get("/t/{$tenant->slug}/billing")->assertOk();
        $this->assertSame(1, $tenant->messes()->count());
    }

    public function test_grace_period_then_expiry_through_the_sweep(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'active', ['current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay()])->create();
        $service = app(SubscriptionService::class);

        $this->travel(2)->days();
        $this->assertSame(1, $service->sweep());
        $s = $tenant->subscription->fresh();
        $this->assertSame(SubscriptionStatus::PastDue, $s->status);
        $this->assertSame(SubscriptionService::FULL, $service->access($tenant)); // within 7-day grace

        $this->travel(7)->days();
        $service->sweep();
        $this->assertSame(SubscriptionStatus::Expired, $tenant->subscription->fresh()->status);
        $this->assertSame(SubscriptionService::READ_ONLY, $service->access($tenant));
        $this->assertSame(['past_due', 'expired'], $s->events()->orderBy('id')->pluck('event')->all());
    }

    public function test_cancellation_keeps_access_until_period_end(): void
    {
        $tenant = Tenant::factory()->onPlan('pro', 'active', ['current_period_start' => now(), 'current_period_end' => now()->addDays(10)])->create();

        $this->actingAs($tenant->owner)->post("/t/{$tenant->slug}/billing/cancel")->assertSessionHasNoErrors();

        $service = app(SubscriptionService::class);
        $this->assertSame(SubscriptionStatus::Cancelled, $tenant->subscription->fresh()->status);
        $this->assertSame(SubscriptionService::FULL, $service->access($tenant));

        $this->travel(11)->days();
        $service->forget();
        $this->assertSame(SubscriptionService::READ_ONLY, $service->access($tenant));
    }

    public function test_features_follow_the_plan(): void
    {
        $tenant = Tenant::factory()->onPlan('free')->create();
        $mess = Mess::factory()->create(['tenant_id' => $tenant->id]);
        app(\App\Services\Portal\PortalSettingsService::class)->save($mess, true, false);
        $base = "/t/{$tenant->slug}/m/{$mess->slug}";

        $this->actingAs($tenant->owner)->get("{$base}/reports/outstanding")->assertOk()->assertDontSee('CSV (Excel)');
        $this->actingAs($tenant->owner)->get("{$base}/reports/outstanding?format=csv")->assertForbidden();
        $this->assertFalse(app(\App\Services\Portal\PortalSettingsService::class)->get($mess)['self_service_meals']);

        Subscription::where('tenant_id', $tenant->id)->update(['plan_id' => SubscriptionPlan::where('slug', 'basic')->value('id')]);
        app(SubscriptionService::class)->forget();
        $this->actingAs($tenant->owner)->get("{$base}/reports/outstanding?format=csv")->assertOk();
    }

    public function test_billing_and_support_are_owner_only_and_tenant_scoped(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $mess = Mess::factory()->create(['tenant_id' => $tenant->id]);
        $admin = $this->memberOf($mess, Role::MESS_ADMIN);
        $invoice = $other->invoices()->create(['subscription_id' => $other->subscription->id, 'plan_id' => $other->subscription->plan_id, 'billing_cycle' => 'monthly',
            'invoice_number' => 'INV-X-1', 'description' => 'x', 'subtotal' => 10, 'total' => 10, 'currency' => 'BDT', 'issued_at' => now(), 'due_at' => now()]);

        $this->actingAs($admin->user)->get("/t/{$tenant->slug}/billing")->assertForbidden();
        $this->actingAs($admin->user)->get("/t/{$tenant->slug}/support")->assertForbidden();
        $this->actingAs($tenant->owner)->get("/t/{$tenant->slug}/billing/invoices/{$invoice->id}")->assertNotFound();
        $this->actingAs($tenant->owner)->get("/t/{$tenant->slug}/billing")->assertOk()->assertSee('Enterprise');
    }
}
