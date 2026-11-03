<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\Tenancy\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    public function create(): View
    {
        return view('tenants.create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->tenants->create($request->user(), $request->validated());

        return redirect()->route('tenant.dashboard', $tenant)->with('status', 'Organization created.');
    }

    public function dashboard(Request $request, Tenant $tenant): View
    {
        $user = $request->user();
        $isOwner = $tenant->owner_id === $user->id;

        $messes = $tenant->messes()
            ->unless($isOwner, fn ($q) => $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('membership_status', MembershipStatus::Active)))
            ->withCount(['members as active_members_count' => fn ($q) => $q->where('membership_status', MembershipStatus::Active)])
            ->orderBy('name')
            ->get();

        return view('tenants.dashboard', [
            'tenant' => $tenant,
            'messes' => $messes,
            'isOwner' => $isOwner,
            'userCount' => $isOwner ? $tenant->users()->count() : null,
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);

        return view('tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->tenants->update($tenant, $request->validated());

        return redirect()->route('tenant.settings.edit')->with('status', 'Organization settings saved.');
    }
}
