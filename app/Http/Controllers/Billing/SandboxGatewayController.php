<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentProviderManager;
use App\Services\Billing\Providers\SandboxGatewayProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stand-in for a provider's hosted checkout page. Its result goes through the
 * same signed-callback path a real gateway uses.
 */
class SandboxGatewayController extends Controller
{
    public function show(Request $request, string $reference, PaymentProviderManager $providers): View
    {
        $payment = $this->payment($request, $reference, $providers);

        return view('billing.sandbox', ['payment' => $payment->load('invoice', 'tenant')]);
    }

    public function pay(Request $request, string $reference, PaymentProviderManager $providers, BillingService $billing): RedirectResponse
    {
        $payment = $this->payment($request, $reference, $providers);
        $outcome = $request->validate(['outcome' => ['required', Rule::in(['paid', 'declined'])]])['outcome'];

        /** @var SandboxGatewayProvider $sandbox */
        $sandbox = $providers->get('sandbox');
        $callback = Request::create('/billing/callback/sandbox', 'POST', $sandbox->signedPayload($payment->provider_reference, $outcome, (string) $payment->amount, $payment->currency));
        $payment = $billing->handleCallback('sandbox', $callback);

        return redirect()->route('billing.index', ['tenant' => $payment->tenant])
            ->with($payment->status === 'paid' ? 'status' : 'error', $payment->status === 'paid' ? 'Payment successful; your plan is active.' : 'The payment was declined; your plan was not changed.');
    }

    private function payment(Request $request, string $reference, PaymentProviderManager $providers): SubscriptionPayment
    {
        abort_unless(array_key_exists('sandbox', $providers->enabled()), 404);
        $payment = SubscriptionPayment::query()->where('provider_reference', $reference)->where('payment_method', 'sandbox')->firstOrFail();
        abort_unless($payment->tenant->owner_id === $request->user()->id, 404);

        return $payment;
    }
}
