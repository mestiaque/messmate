<?php

namespace App\Http\Controllers\Meals;

use App\Enums\PeriodMode;
use App\Enums\PeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\StorePeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Settlement\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function __construct(private readonly AccountingPeriodService $periods) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        return view('periods.index', [
            'mess' => $mess,
            'periods' => $mess->periods()->orderByDesc('start_date')->paginate(24),
            'canManage' => $request->user()->can('managePeriods', $mess),
            'canReopen' => $request->user()->can('reopenPeriods', $mess),
        ]);
    }

    public function store(StorePeriodRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $period = $this->periods->create(
            $mess,
            $request->user(),
            PeriodMode::from($request->input('period_type')),
            $request->input('month'),
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('name'),
            $request->input('notes'),
        );

        return back()->with('status', "Period \"{$period->name}\" created as draft.");
    }

    public function transition(Request $request, Tenant $tenant, Mess $mess, AccountingPeriod $period): RedirectResponse
    {
        $this->authorize('managePeriods', $mess);
        $data = $request->validate(['status' => ['required', Rule::enum(PeriodStatus::class)]]);

        $this->periods->transition($period, PeriodStatus::from($data['status']), $request->user());

        return back()->with('status', "Period \"{$period->name}\" is now {$data['status']}.");
    }

    public function reopen(Request $request, Tenant $tenant, Mess $mess, AccountingPeriod $period, SettlementService $settlements): RedirectResponse
    {
        $this->authorize('reopenPeriods', $mess);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $settlements->reopen($period, $request->user(), $data['reason']);

        return back()->with('status', "Period \"{$period->name}\" reopened. Its settlement was superseded and the charges reversed.");
    }
}
