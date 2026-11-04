<?php

namespace App\Services\Billing\Providers;

use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

/**
 * A payment gateway. Controllers never talk to a gateway directly; they go
 * through BillingService, which only trusts what verifyCallback() returns.
 */
interface PaymentProviderInterface
{
    public function key(): string;

    public function label(): string;

    /**
     * Whether payments are confirmed by a signed provider callback (true) or by a platform admin (false).
     */
    public function usesCallbacks(): bool;

    public function createIntent(SubscriptionPayment $payment): PaymentIntent;

    /**
     * Verify the callback's signature and freshness and return what the provider says happened.
     *
     * @throws InvalidCallbackException
     */
    public function verifyCallback(Request $request): CallbackResult;
}
