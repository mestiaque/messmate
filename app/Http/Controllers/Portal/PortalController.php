<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EntryStatus;
use App\Enums\MealTrackingMode;
use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Meals\MealEntryService;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Portal\MemberPortalService;
use App\Services\Portal\PortalSettingsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The signed-in member's own view of the mess. Everything here is scoped to
 * the membership resolved for the current user, never to a member id from input.
 */
class PortalController extends Controller
{
    public function index(Tenant $tenant, Mess $mess, TenantContext $context, AccountingPeriodService $periods, MemberPortalService $portal, PortalSettingsService $settings): View
    {
        $member = $context->member() ?? abort(403, 'You are not a member of this mess.');
        $period = $periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first();

        return view('portal.index', [
            'mess' => $mess,
            'member' => $member,
            'period' => $period,
            'data' => $portal->summary($mess, $member, $period),
            'settings' => $settings->get($mess),
        ]);
    }

    public function meals(Request $request, Tenant $tenant, Mess $mess, TenantContext $context, PortalSettingsService $settings, AccountingPeriodService $periods): View
    {
        $member = $context->member() ?? abort(403);
        abort_unless($settings->get($mess)['self_service_meals'], 403, 'Self-service meal entry is turned off for this mess.');

        $date = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : now($mess->timezone)->startOfDay();

        return view('portal.meals', [
            'mess' => $mess,
            'member' => $member,
            'date' => $date,
            'entry' => $member->mealEntries()->whereDate('meal_date', $date)->where('status', '!=', EntryStatus::Voided)->with('items')->first(),
            'types' => $mess->mealTypes()->active()->get(),
            'period' => $periods->periodFor($mess, $date),
            'settings' => $settings->get($mess),
        ]);
    }

    public function storeMeals(Request $request, Tenant $tenant, Mess $mess, TenantContext $context, PortalSettingsService $settings, MealEntryService $meals): RedirectResponse
    {
        $member = $context->member() ?? abort(403);
        $config = $settings->get($mess);
        abort_unless($config['self_service_meals'], 403);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'meals' => ['array'],
            'meals.*' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,4'],
            'units' => ['nullable', 'numeric', 'min:0', 'max:20', 'decimal:0,4'],
        ]);
        $date = Carbon::parse($data['date'])->toDateString();

        // Approved entries are locked for members; an admin must correct them.
        if ($meals->findLive($member, $date)?->status === EntryStatus::Approved) {
            throw ValidationException::withMessages(['date' => 'This day is already approved. Ask a manager to change it.']);
        }

        $approve = ! $config['require_approval'];
        $mess->default_meal_tracking_mode === MealTrackingMode::Individual
            ? $meals->saveIndividual($mess, $member, $date, $data['meals'] ?? [], $request->user(), $approve)
            : $meals->saveDailyTotal($mess, $member, $date, $data['units'] ?? '0', $request->user(), $approve);

        return redirect()->route('portal.meals', ['date' => $date])->with('status', $approve ? 'Meals saved.' : 'Meals submitted for approval.');
    }
}
