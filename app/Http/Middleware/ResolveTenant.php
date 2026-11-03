<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves {tenant} from the URL and rejects users who do not belong to it.
 * Non-members get 404 so tenant slugs cannot be probed.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        abort_unless($tenant instanceof Tenant, 404);
        abort_unless($request->user()->belongsToTenant($tenant), 404);
        abort_unless($tenant->isActive(), 403, 'This organization is not active.');

        $this->context->setTenant($tenant);
        URL::defaults(['tenant' => $tenant->slug]);

        return $next($request);
    }
}
