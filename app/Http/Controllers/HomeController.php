<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\Mess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing page after login: every organization and mess the user can enter.
 * This doubles as the tenant / mess switcher.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tenants = $user->accessibleTenants();

        $ownedIds = $tenants->where('owner_id', $user->id)->pluck('id');

        $messes = Mess::query()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->where(function ($q) use ($user, $ownedIds) {
                $q->whereIn('tenant_id', $ownedIds)
                    ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('membership_status', MembershipStatus::Active));
            })
            ->with('tenant:id,name,slug')
            ->orderBy('name')
            ->get()
            ->groupBy('tenant_id');

        return view('home', compact('tenants', 'messes'));
    }
}
