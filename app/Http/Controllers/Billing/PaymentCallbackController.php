<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use App\Services\Billing\Providers\InvalidCallbackException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server endpoint for payment providers. Nothing the browser says
 * about a payment is trusted; only a verified callback changes its status.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $provider, BillingService $billing): JsonResponse
    {
        try {
            $payment = $billing->handleCallback($provider, $request);
        } catch (InvalidCallbackException $e) {
            Log::warning('Rejected payment callback', ['provider' => $provider, 'reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['ok' => false, 'error' => 'invalid callback'], 400);
        }

        return response()->json(['ok' => true, 'status' => $payment->status]);
    }
}
