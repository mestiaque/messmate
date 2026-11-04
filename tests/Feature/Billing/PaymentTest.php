<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Mess;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionPaymentReceived;
use App\Services\Billing\BillingService;
use App\Services\Billing\Providers\SandboxGatewayProvider;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', $slug)->firstOrFail();
    }

    private function sandboxCheckout(Tenant $tenant, string $slug = 'pro', string $cycle = 'monthly'): SubscriptionPayment
    {
        [$payment] = app(BillingService::class)->checkout($tenant, $this->plan($slug), $cycle, 'sandbox', $tenant->owner);

        return $payment;
    }

    private function sendCallback(SubscriptionPayment $payment, string $status = 'paid', ?string $amount = null, ?int $timestamp = null)
    {
        $payload = app(SandboxGatewayProvider::class)->signedPayload($payment->provider_reference, $status, $amount ?? (string) $payment->amount, $payment->currency, $timestamp);

        return $this->postJson('/billing/callback/sandbox', $payload);
    }

    public function test_checkout_creates_invoice_from_plan_price_not_input(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDays(5)])->create();

        $this->actingAs($tenant->owner)->post("/t/{$tenant->slug}/billing/checkout", [
            'plan_id' => $this->plan('pro')->id, 'billing_cycle' => 'yearly', 'provider' => 'sandbox', 'amount' => '1',
        ])->assertRedirect();

        $payment = SubscriptionPayment::firstOrFail();
        $this->assertSame('6990.00', $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('INV-'.now()->format('Y').'-00001', $payment->invoice->invoice_number);
        $this->assertSame('unpaid', $payment->invoice->status);
    }

    public function test_verified_callback_activates_plan_and_duplicates_are_ignored(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDays(5)])->create();
        $payment = $this->sandboxCheckout($tenant);

        $this->sendCallback($payment)->assertOk()->assertJson(['status' => 'paid']);
        $this->sendCallback($payment)->assertOk()->assertJson(['status' => 'paid']);
        $this->sendCallback($payment)->assertOk();

        $subscription = $tenant->subscription->fresh('plan');
        $this->assertSame('pro', $subscription->plan->slug);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_end->between(now()->addMonth()->subMinute(), now()->addMonth()->addMinute()));
        $this->assertSame(1, SubscriptionPayment::where('status', 'paid')->count());
        $this->assertSame(1, $subscription->events()->where('event', 'upgraded')->count());
        $this->assertSame('paid', $payment->invoice->fresh()->status);
        Notification::assertSentToTimes($tenant->owner, SubscriptionPaymentReceived::class, 1);
    }

    public function test_bad_signature_expired_callback_and_unknown_reference_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $payment = $this->sandboxCheckout($tenant);
        $good = app(SandboxGatewayProvider::class)->signedPayload($payment->provider_reference, 'paid', (string) $payment->amount, 'BDT');

        $this->postJson('/billing/callback/sandbox', ['signature' => str_repeat('0', 64)] + $good)->assertStatus(400);
        $this->postJson('/billing/callback/sandbox', ['amount' => '1.00'] + $good)->assertStatus(400); // tampered after signing
        $this->sendCallback($payment, timestamp: time() - 3600)->assertStatus(400);
        $this->postJson('/billing/callback/sandbox', app(SandboxGatewayProvider::class)->signedPayload('MMNOPE', 'paid', '1', 'BDT'))->assertNotFound();
        $this->postJson('/billing/callback/manual', $good)->assertNotFound();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('enterprise', $tenant->subscription->fresh()->plan->slug);
    }

    public function test_declined_or_mismatched_payments_do_not_activate(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDays(5)])->create();

        $declined = $this->sandboxCheckout($tenant);
        $this->sendCallback($declined, 'declined')->assertOk()->assertJson(['status' => 'failed']);

        // A correctly signed callback for less than we charged.
        $short = $this->sandboxCheckout($tenant);
        $this->sendCallback($short, 'paid', '1.00')->assertOk()->assertJson(['status' => 'failed']);

        $s = $tenant->subscription->fresh('plan');
        $this->assertSame('basic', $s->plan->slug);
        $this->assertSame(SubscriptionStatus::Trial, $s->status);
        $this->assertSame('amount_mismatch', $short->fresh()->gateway_response['reason']);
        $this->assertSame(0, SubscriptionPayment::where('status', 'paid')->count());

        // A late "paid" for an already failed payment is ignored.
        $this->sendCallback($declined)->assertOk()->assertJson(['status' => 'failed']);
        $this->assertSame('basic', $tenant->subscription->fresh()->plan->slug);
    }

    public function test_renewal_extends_from_the_current_end_date(): void
    {
        $end = now()->addDays(10)->startOfSecond();
        $tenant = Tenant::factory()->onPlan('pro', 'active', ['current_period_start' => now()->subDays(20), 'current_period_end' => $end])->create();

        $this->sendCallback($this->sandboxCheckout($tenant, 'pro', 'monthly'))->assertOk();

        $s = $tenant->subscription->fresh();
        $this->assertSame($end->copy()->addMonth()->toDateTimeString(), $s->current_period_end->toDateTimeString());
        $this->assertSame(1, $s->events()->where('event', 'renewed')->count());
    }

    public function test_remaining_paid_time_is_kept_when_cycle_or_plan_changes(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDays(5)])->create();

        $this->sendCallback($this->sandboxCheckout($tenant, 'pro', 'yearly'))->assertOk();
        $yearEnd = $tenant->subscription->fresh()->current_period_end;
        $this->assertTrue($yearEnd->between(now()->addYear()->subMinute(), now()->addYear()->addMinute()));

        // Switching to monthly billing adds a month after the paid year.
        $this->sendCallback($this->sandboxCheckout($tenant, 'pro', 'monthly'))->assertOk();
        $this->assertSame($yearEnd->copy()->addMonth()->toDateTimeString(), $tenant->subscription->fresh()->current_period_end->toDateTimeString());

        // Upgrading applies the new plan now and still keeps the paid time.
        $before = $tenant->subscription->fresh()->current_period_end;
        $this->sendCallback($this->sandboxCheckout($tenant, 'enterprise', 'monthly'))->assertOk();
        $s = $tenant->subscription->fresh('plan');
        $this->assertSame('enterprise', $s->plan->slug);
        $this->assertSame($before->copy()->addMonth()->toDateTimeString(), $s->current_period_end->toDateTimeString());
    }

    public function test_expired_tenant_can_renew_and_regain_write_access(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'expired', ['current_period_end' => now()->subMonth()])->create();
        $service = app(SubscriptionService::class);
        $this->assertFalse($service->isWritable($tenant));

        $this->actingAs($tenant->owner)->post("/t/{$tenant->slug}/billing/checkout", ['plan_id' => $this->plan('basic')->id, 'billing_cycle' => 'monthly', 'provider' => 'sandbox'])->assertRedirect();
        $this->sendCallback(SubscriptionPayment::firstOrFail())->assertOk();

        $service->forget();
        $this->assertTrue($service->isWritable($tenant->fresh()));
        $this->assertTrue($tenant->subscription->fresh()->current_period_end->isFuture());
    }

    public function test_downgrade_is_blocked_when_usage_exceeds_the_new_plan(): void
    {
        $tenant = Tenant::factory()->create();
        Mess::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAs($tenant->owner)->post("/t/{$tenant->slug}/billing/checkout", ['plan_id' => $this->plan('basic')->id, 'billing_cycle' => 'monthly', 'provider' => 'sandbox'])
            ->assertSessionHasErrors('plan');
        $this->actingAs($tenant->owner)->post("/t/{$tenant->slug}/billing/checkout", ['plan_id' => $this->plan('free')->id, 'billing_cycle' => 'monthly', 'provider' => 'manual'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, SubscriptionPayment::count());
        $this->assertSame(3, $tenant->messes()->count());
    }

    public function test_manual_payment_needs_reference_and_platform_confirmation(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDays(5)])->create();
        $url = "/t/{$tenant->slug}/billing/checkout";

        $this->actingAs($tenant->owner)->post($url, ['plan_id' => $this->plan('pro')->id, 'billing_cycle' => 'monthly', 'provider' => 'manual'])->assertSessionHasErrors('transaction_reference');
        $this->actingAs($tenant->owner)->post($url, ['plan_id' => $this->plan('pro')->id, 'billing_cycle' => 'monthly', 'provider' => 'manual', 'transaction_reference' => 'BKX123'])->assertSessionHasNoErrors();

        $payment = SubscriptionPayment::firstOrFail();
        $this->assertSame('basic', $tenant->subscription->fresh()->plan->slug); // not active until confirmed

        $this->actingAs($tenant->owner)->post("/platform/payments/{$payment->id}/decide", ['action' => 'confirm'])->assertForbidden();
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->get('/platform/payments')->assertOk()->assertSee('BKX123');
        $this->actingAs($admin)->post("/platform/payments/{$payment->id}/decide", ['action' => 'confirm'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/platform/payments/{$payment->id}/decide", ['action' => 'confirm'])->assertSessionHasErrors('payment');

        $this->assertSame('pro', $tenant->subscription->fresh()->plan->slug);
        $this->assertSame($admin->id, $payment->fresh()->confirmed_by);
    }

    public function test_sandbox_page_flow_and_ownership(): void
    {
        $tenant = Tenant::factory()->onPlan('basic', 'trial', ['trial_ends_at' => now()->addDays(5)])->create();
        $payment = $this->sandboxCheckout($tenant);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/billing/sandbox/{$payment->provider_reference}")->assertNotFound();
        $this->actingAs($tenant->owner)->get("/billing/sandbox/{$payment->provider_reference}")->assertOk()->assertSee('699.00');
        $this->actingAs($tenant->owner)->post("/billing/sandbox/{$payment->provider_reference}", ['outcome' => 'paid'])->assertRedirect("/t/{$tenant->slug}/billing");

        $this->assertSame('pro', $tenant->subscription->fresh()->plan->slug);
    }

    public function test_invoice_numbers_are_sequential_per_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->sandboxCheckout($a);
        $this->sandboxCheckout($a);
        $this->sandboxCheckout($b);

        $year = now()->format('Y');
        $this->assertSame(["INV-{$year}-00001", "INV-{$year}-00002"], $a->invoices()->orderBy('id')->pluck('invoice_number')->all());
        $this->assertSame(["INV-{$year}-00001"], $b->invoices()->pluck('invoice_number')->all());
    }

    public function test_free_and_inactive_plans_cannot_be_checked_out(): void
    {
        $tenant = Tenant::factory()->create();
        $this->plan('pro')->update(['is_active' => false]);

        foreach (['free', 'pro'] as $slug) {
            try {
                app(BillingService::class)->checkout($tenant, $this->plan($slug), 'monthly', 'sandbox', $tenant->owner);
                $this->fail("Checked out {$slug}.");
            } catch (ValidationException) {
            }
        }
    }
}
