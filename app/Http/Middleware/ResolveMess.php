<?php

namespace App\Http\Middleware;

use App\Models\Mess;
use App\Services\Access\MessAccessService;
use App\Support\Tenancy\Permissions;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves {mess} (already scoped to {tenant} by scoped route bindings) and
 * requires the user to have access to it.
 */
class ResolveMess
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MessAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $mess = $request->route('mess');
        $user = $request->user();

        abort_unless($mess instanceof Mess && $mess->tenant_id === $this->context->tenant()?->id, 404);
        abort_unless($this->access->allows($user, $mess, Permissions::MESS_VIEW), 404);

        $mess->setRelation('tenant', $this->context->tenant());
        $this->context->setMess($mess, $user->activeMembershipIn($mess));
        URL::defaults(['mess' => $mess->slug]);

        return $next($request);
    }
}
