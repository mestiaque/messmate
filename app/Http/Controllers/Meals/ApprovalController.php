<?php

namespace App\Http\Controllers\Meals;

use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\GuestMeal;
use App\Models\MealAdjustment;
use App\Models\MealEntry;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Meals\MealApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private readonly MealApprovalService $approvals) {}

    public function index(Tenant $tenant, Mess $mess): View
    {
        $this->authorize('approveMeals', $mess);

        $pending = fn ($relation) => $mess->{$relation}()->where('status', EntryStatus::Submitted);

        return view('meals.approvals', [
            'mess' => $mess,
            'entries' => $pending('mealEntries')->with('member:id,member_code,full_name', 'items.mealType:id,name', 'creator:id,name')->orderBy('meal_date')->limit(200)->get(),
            'guests' => $pending('guestMeals')->with('sponsor:id,full_name', 'creator:id,name')->orderBy('meal_date')->limit(200)->get(),
            'adjustments' => $pending('mealAdjustments')->with('member:id,member_code,full_name', 'period:id,name', 'creator:id,name')->limit(200)->get(),
        ]);
    }

    public function decide(Request $request, Tenant $tenant, Mess $mess, string $type, int $id): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'void'])],
            'reason' => ['required_unless:action,approve', 'nullable', 'string', 'max:255'],
        ]);

        $this->authorize($data['action'] === 'void' ? 'manageMeals' : 'approveMeals', $mess);

        $record = $this->find($mess, $type, $id);

        match ($data['action']) {
            'approve' => $this->approvals->approve($record, $request->user()),
            'reject' => $this->approvals->reject($record, $request->user(), $data['reason']),
            'void' => $this->approvals->void($record, $request->user(), $data['reason']),
        };

        return back()->with('status', 'Record '.['approve' => 'approved', 'reject' => 'rejected', 'void' => 'voided'][$data['action']].'.');
    }

    public function bulkApprove(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('approveMeals', $mess);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'regex:/^(meal-entries|guest-meals|adjustments):\d+$/'],
        ]);

        DB::transaction(function () use ($data, $mess, $request) {
            foreach ($data['ids'] as $key) {
                [$type, $id] = explode(':', $key);
                $this->approvals->approve($this->find($mess, $type, (int) $id), $request->user());
            }
        });

        return back()->with('status', count($data['ids']).' record(s) approved.');
    }

    private function find(Mess $mess, string $type, int $id): MealEntry|GuestMeal|MealAdjustment
    {
        $relation = match ($type) {
            'meal-entries' => 'mealEntries',
            'guest-meals' => 'guestMeals',
            'adjustments' => 'mealAdjustments',
            default => abort(404),
        };

        return $mess->{$relation}()->findOrFail($id);
    }
}
