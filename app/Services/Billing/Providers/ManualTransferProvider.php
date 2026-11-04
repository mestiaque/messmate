<?php

namespace App\Services\Billing\Providers;

use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

/**
 * Bank transfer / mobile wallet send-money: the tenant pays outside the app and
 * enters the reference; a platform admin confirms it.
 */
class ManualTransferProvider implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Bank / bKash (manual)';
    }

    public function usesCallbacks(): bool
    {
        return false;
    }

    public function createIntent(SubscriptionPayment $payment): PaymentIntent
    {
        return new PaymentIntent(null, PlatformSetting::get('payment_instructions', 'Send the amount to the account published by the platform and keep the transaction ID.'));
    }

    public function verifyCallback(Request $request): CallbackResult
    {
        throw new InvalidCallbackException('Manual payments have no callback.');
    }
}
