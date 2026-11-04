<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentProviderManager;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tenant-owner billing: plan, usage, checkout, invoices, cancellation.
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly BillingService $billing,
    ) {}

    public function index(Tenant $tenant, PaymentProviderManager $providers): View
    {
        $this->authorize('update', $tenant);

        return view('billing.index', [
            'tenant' => $tenant,
            'subscription' => $this->subscriptions->current($tenant),
            'access' => $this->subscriptions->access($tenant),
            'usage' => $this->subscriptions->usage($tenant),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'providers' => $providers->enabled(),
            'invoices' => $tenant->invoices()->with('payments')->latest('id')->limit(20)->get(),
            'events' => $this->subscriptions->current($tenant)->events()->latest('id')->limit(10)->get(),
        ]);
    }

    public function checkout(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'provider' => ['required', 'string', 'max:30'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
        ]);
        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($data['plan_id']);

        if ($plan->isFree()) {
            $this->subscriptions->switchToFree($tenant, $plan, $request->user());

            return back()->with('status', 'Switched to the Free plan.');
        }

        [$payment, $intent] = $this->billing->checkout($tenant, $plan, $data['billing_cycle'], $data['provider'], $request->user(), $data['transaction_reference'] ?? null);

        return $intent->redirectUrl
            ? redirect()->away($intent->redirectUrl)
            : back()->with('status', "Invoice {$payment->invoice->invoice_number} created. We will activate {$plan->name} once your transfer ({$payment->transaction_reference}) is confirmed.");
    }

    public function cancel(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);
        $subscription = $this->subscriptions->cancel($tenant, $request->user());

        return back()->with('status', 'Subscription cancelled. You keep full access until '.$subscription->current_period_end->format('d M Y').'.');
    }

    public function invoice(Tenant $tenant, Invoice $invoice): View
    {
        $this->authorize('update', $tenant);

        return view('billing.invoice', ['tenant' => $tenant, 'invoice' => $invoice->load('plan', 'payments')]);
    }
}
