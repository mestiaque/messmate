<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a subscription has lapsed the tenant becomes read-only: viewing and
 * exporting still work, data is never deleted, and billing/support stay open
 * so the tenant can renew.
 */
class EnsureSubscriptionWritable
{
    private const ALWAYS_ALLOWED = ['billing.*', 'support.*'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant && ! $request->isMethodSafe() && ! $request->routeIs(...self::ALWAYS_ALLOWED) && ! $this->subscriptions->isWritable($tenant)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Subscription inactive: read-only.'], 402);
            }

            return back()->withErrors(['subscription' => 'Your subscription has ended, so this organization is read-only. Renew it on the billing page to make changes.']);
        }

        return $next($request);
    }
}
