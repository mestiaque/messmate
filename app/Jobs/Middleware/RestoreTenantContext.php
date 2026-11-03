<?php

namespace App\Jobs\Middleware;

use App\Models\Mess;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;

/**
 * Re-establishes the tenant/mess captured at dispatch time (see TenantAware)
 * and clears it afterwards so a long-running worker never leaks context.
 */
class RestoreTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        $context = app(TenantContext::class);
        $context->clear();

        try {
            if ($job->tenantId) {
                $tenant = Tenant::findOrFail($job->tenantId);
                $context->setTenant($tenant);

                if ($job->messId) {
                    $context->setMess($tenant->messes()->findOrFail($job->messId));
                }
            }

            $next($job);
        } finally {
            $context->clear();
        }
    }
}
