<?php

namespace App\Http\Controllers\Meals;

use App\Enums\MealTrackingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\StoreDailyMealsRequest;
use App\Models\MealEntry;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\Tenant;
use App\Services\Meals\MealEntryService;
use App\Services\Meals\MealSummaryService;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MealEntryController extends Controller
{
    public function __construct(
        private readonly MealEntryService $entries,
        private readonly MealSummaryService $summary,
        private readonly AccountingPeriodService $periods,
    ) {}

    /**
     * Bulk entry grid for one date: every member effective on that date.
     */
    public function daily(Request $request, Tenant $tenant, Mess $mess): View
    {
        $this->authorize('viewMeals', $mess);

        $date = $this->dateParam($request, 'date', $mess);
        $mode = MealTrackingMode::tryFrom((string) $request->query('mode')) ?? $mess->default_meal_tracking_mode;

        $members = $mess->members()->effectiveOn($date)->orderBy('member_code')->get();
        $existing = $mess->mealEntries()
            ->whereDate('meal_date', $date)
            ->where('status', '!=', 'voided')
            ->with('items')
            ->get()
            ->keyBy('member_id');

        return view('meals.daily', [
            'mess' => $mess,
            'date' => $date,
            'mode' => $mode,
            'members' => $members,
            'existing' => $existing,
            'types' => $mess->mealTypes()->active()->get(),
            'period' => $this->periods->periodFor($mess, $date),
            'summary' => $this->summary->daily($mess, $date->toDateString()),
            'canManage' => $request->user()->can('manageMeals', $mess),
            'canApprove' => $request->user()->can('approveMeals', $mess),
        ]);
    }

    public function storeDaily(StoreDailyMealsRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $date = Carbon::parse($request->input('date'))->toDateString();
        $mode = MealTrackingMode::from($request->input('tracking_mode'));
        $approve = $request->boolean('approve') && $request->user()->can('approveMeals', $mess);
        $members = $mess->members()->whereIn('id', $request->input('selected'))->get();

        if ($members->count() !== count(array_unique($request->input('selected')))) {
            throw ValidationException::withMessages(['selected' => 'One or more selected members do not belong to this mess.']);
        }

        $saved = 0;

        // All-or-nothing: one invalid row rolls back the whole batch.
        DB::transaction(function () use ($request, $mess, $members, $date, $mode, $approve, &$saved) {
            foreach ($members as $member) {
                $entry = $mode === MealTrackingMode::Individual
                    ? $this->entries->saveIndividual($mess, $member, $date, (array) $request->input("meals.{$member->id}", []), $request->user(), $approve)
                    : $this->entries->saveDailyTotal(
                        $mess, $member, $date, $request->input("units.{$member->id}"), $request->user(), $approve,
                        rawCount: $request->input("raw_count.{$member->id}"),
                        factor: $request->input('conversion_factor'),
                    );

                $saved += $entry ? 1 : 0;
            }
        });

        return redirect()->route('meals.daily', ['date' => $date, 'mode' => $mode->value])
            ->with('status', "Saved meals for {$saved} member(s) on {$date}.");
    }

    /**
     * Month calendar of units per day, for the whole mess or one member.
     * Users without meals.view only ever see their own calendar.
     */
    public function calendar(Request $request, Tenant $tenant, Mess $mess, TenantContext $context): View
    {
        $canViewAll = $request->user()->can('viewMeals', $mess);
        $own = $context->member();

        abort_unless($canViewAll || $own, 403);

        $month = Carbon::createFromFormat('!Y-m', $request->query('month', now($mess->timezone)->format('Y-m')) ?: now()->format('Y-m'));
        $member = null;

        if (! $canViewAll) {
            $member = $own;
        } elseif ($request->filled('member')) {
            $member = $mess->members()->findOrFail($request->integer('member'));
        }

        return view('meals.calendar', [
            'mess' => $mess,
            'month' => $month,
            'member' => $member,
            'members' => $canViewAll ? $mess->members()->orderBy('member_code')->get(['id', 'member_code', 'full_name']) : collect(),
            'days' => $this->summary->calendar($mess, $month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $member),
            'canViewAll' => $canViewAll,
        ]);
    }

    private function dateParam(Request $request, string $key, Mess $mess): Carbon
    {
        try {
            return $request->filled($key) ? Carbon::parse($request->query($key))->startOfDay() : now($mess->timezone)->startOfDay();
        } catch (\Throwable) {
            return now($mess->timezone)->startOfDay();
        }
    }
}
