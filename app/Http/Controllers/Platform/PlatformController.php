<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    public function dashboard(): View
    {
        return view('platform.dashboard', [
            'stats' => [
                'Organizations' => Tenant::count(),
                'Active organizations' => Tenant::where('status', TenantStatus::Active)->count(),
                'Messes' => Mess::count(),
                'Users' => User::count(),
                'Mess members' => MessMember::count(),
            ],
            'recentTenants' => Tenant::with('owner:id,name,email')->latest()->limit(10)->get(),
        ]);
    }

    public function tenants(Request $request): View
    {
        $tenants = Tenant::query()
            ->with('owner:id,name,email,phone')
            ->withCount('messes')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('platform.tenants.index', compact('tenants'));
    }

    public function showTenant(Tenant $tenant): View
    {
        $tenant->load('owner')->loadCount(['messes', 'users']);

        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'messes' => $tenant->messes()->withCount('members')->orderBy('name')->get(),
        ]);
    }

    public function suspendTenant(Tenant $tenant): RedirectResponse
    {
        $this->tenants->setStatus($tenant, TenantStatus::Suspended);

        return back()->with('status', 'Organization suspended.');
    }

    public function activateTenant(Tenant $tenant): RedirectResponse
    {
        $this->tenants->setStatus($tenant, TenantStatus::Active);

        return back()->with('status', 'Organization activated.');
    }
}
