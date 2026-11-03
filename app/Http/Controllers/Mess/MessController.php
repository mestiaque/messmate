<?php

namespace App\Http\Controllers\Mess;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreMessRequest;
use App\Http\Requests\Mess\UpdateMessRequest;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Access\MessAccessService;
use App\Services\Tenancy\MessService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessController extends Controller
{
    public function __construct(private readonly MessService $messes) {}

    public function create(Tenant $tenant): View
    {
        $this->authorize('createMess', $tenant);

        return view('messes.create', compact('tenant'));
    }

    public function store(StoreMessRequest $request, Tenant $tenant): RedirectResponse
    {
        $mess = $this->messes->create(
            $tenant,
            $request->user(),
            $request->safe()->except('join_as_member'),
            $request->boolean('join_as_member'),
        );

        return redirect()->route('mess.dashboard', ['mess' => $mess])->with('status', 'Mess created.');
    }

    public function dashboard(Request $request, Tenant $tenant, Mess $mess, TenantContext $context, MessAccessService $access): View
    {
        $member = $context->member()?->load('roles:id,name');

        return view('messes.dashboard', [
            'mess' => $mess,
            'member' => $member,
            'permissions' => $access->permissionsFor($request->user(), $mess),
            'activeMembers' => $mess->members()->where('membership_status', MembershipStatus::Active)->count(),
            'totalMembers' => $mess->members()->count(),
        ]);
    }

    public function edit(Tenant $tenant, Mess $mess): View
    {
        $this->authorize('update', $mess);

        return view('messes.edit', compact('mess'));
    }

    public function update(UpdateMessRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->messes->update($mess, $request->validated());

        return redirect()->route('mess.edit')->with('status', 'Mess settings saved.');
    }
}
