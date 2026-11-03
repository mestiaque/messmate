<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RestoreTenantContext;
use App\Support\Tenancy\TenantContext;

/**
 * For queued jobs that must run inside a tenant. Call captureTenantContext()
 * from the job constructor; the job then runs with that context restored.
 */
trait TenantAware
{
    public ?int $tenantId = null;

    public ?int $messId = null;

    protected function captureTenantContext(): void
    {
        $context = app(TenantContext::class);
        $this->tenantId = $context->tenant()?->id;
        $this->messId = $context->mess()?->id;
    }

    public function middleware(): array
    {
        return [new RestoreTenantContext];
    }
}
