<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionPaymentReceived;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\Providers\CallbackResult;
use App\Services\Billing\Providers\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout → invoice + pending payment → provider → verified callback or admin
 * confirmation → subscription updated. Amounts always come from the plan, and
 * a payment can be applied exactly once.
 */
class BillingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentProviderManager $providers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{0: SubscriptionPayment, 1: PaymentIntent}
     */
    public function checkout(Tenant $tenant, SubscriptionPlan $plan, string $cycle, string $providerKey, User $actor, ?string $reference = null): array
    {
        if (! $plan->is_active || $plan->isFree() || ! in_array($cycle, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages(['plan' => 'Choose an available paid plan and billing cycle.']);
        }

        $provider = $this->providers->get($providerKey);

        if (! $provider->usesCallbacks() && ! $reference) {
            throw ValidationException::withMessages(['transaction_reference' => 'Enter the transaction ID of your transfer.']);
        }

        $this->subscriptions->assertFits($tenant, $plan);
        $subscription = $this->subscriptions->current($tenant);
        $amount = $plan->price($cycle);

        $payment = DB::transaction(function () use ($tenant, $plan, $cycle, $provider, $actor, $reference, $subscription, $amount) {
            $invoice = $tenant->invoices()->create([
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'invoice_number' => $this->nextInvoiceNumber($tenant),
                'description' => "{$plan->name} plan, ".($cycle === 'yearly' ? '1 year' : '1 month'),
                'subtotal' => $amount,
                'total' => $amount,
                'currency' => $plan->currency,
                'issued_at' => now(),
                'due_at' => now()->addDays(config('billing.invoice_due_days')),
            ]);

            $payment = $invoice->payments()->create([
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenant->id,
                'amount' => $amount,
                'currency' => $plan->currency,
                'payment_method' => $provider->key(),
                'transaction_reference' => $reference,
                'provider_reference' => 'MM'.Str::upper(Str::random(18)),
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);

            $this->subscriptions->event($subscription, 'checkout_started', ['invoice' => $invoice->invoice_number, 'plan' => $plan->slug, 'cycle' => $cycle, 'provider' => $provider->key()], $actor);

            return $payment;
        });

        return [$payment, $provider->createIntent($payment)];
    }

    /**
     * Process a provider callback. Duplicates and replays of a settled payment are no-ops.
     */
    public function handleCallback(string $providerKey, Request $request): SubscriptionPayment
    {
        $provider = $this->providers->get($providerKey);
        abort_unless($provider->usesCallbacks(), 404);
        $result = $provider->verifyCallback($request);

        return DB::transaction(function () use ($providerKey, $result) {
            $payment = SubscriptionPayment::query()->lockForUpdate()->where('provider_reference', $result->reference)->first();
            abort_unless($payment && $payment->payment_method === $providerKey, 404);

            if ($payment->status !== 'pending') {
                return $payment; // already settled: idempotent
            }

            if (! $result->success) {
                return $this->fail($payment, 'declined', $result);
            }

            // Never trust the callback's amount blindly: it must match what we charged.
            if (bccomp($result->amount, (string) $payment->amount, 2) !== 0 || strtoupper($result->currency) !== $payment->currency) {
                return $this->fail($payment, 'amount_mismatch', $result);
            }

            return $this->markPaid($payment, null, $result->raw);
        });
    }

    public function confirmManual(SubscriptionPayment $payment, User $admin): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $admin) {
            $payment = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->payment_method !== 'manual' || $payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Only pending manual payments can be confirmed.']);
            }

            return $this->markPaid($payment, $admin, ['confirmed_by' => $admin->email]);
        });
    }

    public function rejectManual(SubscriptionPayment $payment, User $admin, string $reason): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $admin, $reason) {
            $payment = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'This payment is already settled.']);
            }

            $payment->forceFill(['status' => 'failed', 'confirmed_by' => $admin->id, 'gateway_response' => ['rejected' => $reason]])->save();
            $this->subscriptions->event($payment->subscription, 'payment_failed', ['reference' => $payment->provider_reference, 'reason' => $reason], $admin);

            return $payment;
        });
    }

    private function markPaid(SubscriptionPayment $payment, ?User $admin, array $raw): SubscriptionPayment
    {
        $payment->forceFill(['status' => 'paid', 'paid_at' => now(), 'confirmed_by' => $admin?->id, 'gateway_response' => $raw])->save();
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
        $invoice->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $this->subscriptions->applyInvoice($invoice, $admin);
        $this->audit->log('subscription.payment_received', $payment, new: ['amount' => $payment->amount, 'invoice' => $invoice->invoice_number], tenantId: $payment->tenant_id);

        $owner = $payment->tenant->owner;
        if ($owner->email) {
            $owner->notify(new SubscriptionPaymentReceived($invoice));
        }

        return $payment;
    }

    private function fail(SubscriptionPayment $payment, string $reason, CallbackResult $result): SubscriptionPayment
    {
        $payment->forceFill(['status' => 'failed', 'gateway_response' => $result->raw + ['reason' => $reason]])->save();
        $this->subscriptions->event($payment->subscription, 'payment_failed', ['reference' => $payment->provider_reference, 'reason' => $reason]);

        return $payment;
    }

    /**
     * INV-YYYY-00001, sequential per tenant (unique per tenant in the database).
     */
    private function nextInvoiceNumber(Tenant $tenant): string
    {
        Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();
        $count = Invoice::query()->where('tenant_id', $tenant->id)->count();

        return 'INV-'.now()->format('Y').'-'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
