<?php

namespace App\Http\Controllers\Meals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\StoreGuestMealRequest;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Meals\GuestMealService;
use App\Services\Periods\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestMealController extends Controller
{
    public function __construct(private readonly GuestMealService $guests) {}

    public function index(Request $request, Tenant $tenant, Mess $mess, AccountingPeriodService $periods): View
    {
        $this->authorize('viewMeals', $mess);

        $period = $request->filled('period')
            ? $mess->periods()->findOrFail($request->integer('period'))
            : ($periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first());

        return view('meals.guests', [
            'mess' => $mess,
            'period' => $period,
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'guests' => $period ? $period->guestMeals()->with('sponsor:id,full_name', 'mealType:id,name', 'period:id,name,status')->orderByDesc('meal_date')->paginate(50)->withQueryString() : null,
            'types' => $mess->mealTypes()->active()->get(),
            'members' => $mess->members()->where('membership_status', 'active')->orderBy('member_code')->get(['id', 'member_code', 'full_name']),
            'canManage' => $request->user()->can('manageMeals', $mess),
            'canApprove' => $request->user()->can('approveMeals', $mess),
        ]);
    }

    public function store(StoreGuestMealRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->guests->create(
            $mess,
            $request->safe()->except('approve'),
            $request->user(),
            $request->boolean('approve') && $request->user()->can('approveMeals', $mess),
        );

        return back()->with('status', 'Guest meal recorded.');
    }
}
