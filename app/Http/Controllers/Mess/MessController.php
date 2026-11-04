<?php

namespace App\Http\Controllers\Mess;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreMessRequest;
use App\Http\Requests\Mess\UpdateMessRequest;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Dashboard\MessDashboardService;
use App\Services\Periods\AccountingPeriodService;
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

    /**
     * Admin dashboard for users who can see meals or finance; members without
     * those permissions land on their own portal.
     */
    public function dashboard(Request $request, Tenant $tenant, Mess $mess, TenantContext $context, AccountingPeriodService $periods, MessDashboardService $dashboard): View|RedirectResponse
    {
        $user = $request->user();
        $isAdmin = $user->can('viewFinance', $mess) || $user->can('viewMeals', $mess) || $user->can('update', $mess);

        if (! $isAdmin && $context->member()) {
            return redirect()->route('portal.index');
        }

        $period = $request->filled('period')
            ? $mess->periods()->findOrFail($request->integer('period'))
            : ($periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first());

        return view('messes.dashboard', [
            'mess' => $mess,
            'member' => $context->member()?->load('roles:id,name'),
            'period' => $period,
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'summary' => $period ? $dashboard->summary($mess, $period) : null,
            'canFinance' => $user->can('viewFinance', $mess),
            'activeMembers' => $mess->members()->where('membership_status', MembershipStatus::Active)->count(),
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
