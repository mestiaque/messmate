<?php

namespace App\Services\Billing\Providers;

use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

/**
 * Test gateway that behaves like a hosted checkout: the payer is redirected to a
 * page that "pays" or "declines", and the result returns as an HMAC-signed
 * callback. Use it to exercise the full flow; disable it in production.
 */
class SandboxGatewayProvider implements PaymentProviderInterface
{
    public function key(): string
    {
        return 'sandbox';
    }

    public function label(): string
    {
        return 'Sandbox (test only)';
    }

    public function usesCallbacks(): bool
    {
        return true;
    }

    public function createIntent(SubscriptionPayment $payment): PaymentIntent
    {
        return new PaymentIntent(route('billing.sandbox.show', ['reference' => $payment->provider_reference]));
    }

    /**
     * @return array<string, string> payload including its signature
     */
    public function signedPayload(string $reference, string $status, string $amount, string $currency, ?int $timestamp = null): array
    {
        $payload = ['reference' => $reference, 'status' => $status, 'amount' => $amount, 'currency' => $currency, 'timestamp' => (string) ($timestamp ?? time())];

        return $payload + ['signature' => $this->sign($payload)];
    }

    public function verifyCallback(Request $request): CallbackResult
    {
        $payload = $request->only(['reference', 'status', 'amount', 'currency', 'timestamp']);

        if (count(array_filter($payload, 'is_string')) !== 5 || ! is_string($request->input('signature'))) {
            throw new InvalidCallbackException('Malformed callback.');
        }

        if (! hash_equals($this->sign($payload), $request->input('signature'))) {
            throw new InvalidCallbackException('Bad signature.');
        }

        if (abs(time() - (int) $payload['timestamp']) > config('billing.sandbox.max_age_seconds')) {
            throw new InvalidCallbackException('Callback expired.');
        }

        return new CallbackResult($payload['reference'], $payload['status'] === 'paid', $payload['amount'], $payload['currency'], $payload);
    }

    private function sign(array $payload): string
    {
        return hash_hmac('sha256', implode('|', [$payload['reference'], $payload['status'], $payload['amount'], $payload['currency'], $payload['timestamp']]), (string) config('billing.sandbox.secret'));
    }
}
