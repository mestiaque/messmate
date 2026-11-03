<?php

namespace App\Http\Controllers\Meals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\StoreMealAdjustmentRequest;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Meals\MealAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MealAdjustmentController extends Controller
{
    public function __construct(private readonly MealAdjustmentService $adjustments) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $this->authorize('viewMeals', $mess);

        return view('meals.adjustments', [
            'mess' => $mess,
            'adjustments' => $mess->mealAdjustments()->with('member:id,member_code,full_name', 'period:id,name,status', 'creator:id,name')->latest()->paginate(50),
            'periods' => $mess->periods()->where('status', 'open')->orderByDesc('start_date')->get(['id', 'name']),
            'members' => $mess->members()->orderBy('member_code')->get(['id', 'member_code', 'full_name']),
            'canManage' => $request->user()->can('manageMeals', $mess),
            'canApprove' => $request->user()->can('approveMeals', $mess),
        ]);
    }

    public function store(StoreMealAdjustmentRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->adjustments->create(
            $mess->periods()->findOrFail($request->integer('period_id')),
            $mess->members()->findOrFail($request->integer('member_id')),
            (string) $request->input('adjustment_units'),
            $request->input('reason'),
            $request->user(),
            $request->boolean('approve') && $request->user()->can('approveMeals', $mess),
        );

        return back()->with('status', 'Adjustment recorded.');
    }
}
