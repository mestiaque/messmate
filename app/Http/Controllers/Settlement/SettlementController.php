<?php

namespace App\Http\Controllers\Settlement;

use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\MonthlySnapshot;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Settlement\SettlementPolicy;
use App\Services\Settlement\SettlementService;
use App\Services\Settlement\SettlementSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function __construct(private readonly SettlementService $settlements) {}

    /**
     * Settlement for a period: the saved final version when there is one,
     * otherwise a live preview of the calculation.
     */
    public function index(Request $request, Tenant $tenant, Mess $mess, AccountingPeriodService $periods, SettlementSettingsService $settings): View
    {
        $this->authorize('viewFinance', $mess);

        $period = $request->filled('period')
            ? $mess->periods()->findOrFail($request->integer('period'))
            : ($periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first());

        $final = $period?->snapshots()->where('status', MonthlySnapshot::FINAL)->first();
        $draft = $period?->snapshots()->where('status', MonthlySnapshot::DRAFT)->first();

        return view('settlements.index', [
            'mess' => $mess,
            'period' => $period,
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'final' => $final,
            'draft' => $draft,
            'result' => $final?->calculation_details ?? ($period ? $this->settlements->calculate($period) : null),
            'settlementsByMember' => ($final ?? $draft)?->settlements()->get()->keyBy('member_id') ?? collect(),
            'history' => $period?->snapshots()->where('status', '!=', MonthlySnapshot::DRAFT)->orderByDesc('calculation_version')->get() ?? collect(),
            'policy' => $settings->get($mess),
            'canManage' => $request->user()->can('manageSettlements', $mess),
            'pending' => $period ? $periods->pendingCount($period) : 0,
        ]);
    }

    public function updateSettings(Request $request, Tenant $tenant, Mess $mess, SettlementSettingsService $settings): RedirectResponse
    {
        $this->authorize('manageSettlements', $mess);
        $data = $request->validate([
            'strategy' => ['required', Rule::in(SettlementPolicy::STRATEGIES)],
            'custom_rate' => ['required_if:strategy,custom', 'nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,6'],
            'equal_split_basis' => ['required', Rule::in(SettlementPolicy::SPLIT_BASES)],
        ]);

        $settings->save($mess, SettlementPolicy::fromArray($data));

        return back()->with('status', 'Settlement settings saved. They apply to the next calculation; final settlements keep the settings they were made with.');
    }

    public function draft(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('manageSettlements', $mess);
        $period = $mess->periods()->findOrFail($request->integer('period_id'));

        $snapshot = $this->settlements->generateDraft($period, $request->user());

        return back()->with('status', "Draft settlement saved (version {$snapshot->calculation_version}). It can be regenerated until the period is finalized.");
    }

    public function finalize(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('manageSettlements', $mess);
        $period = $mess->periods()->findOrFail($request->integer('period_id'));

        $snapshot = $this->settlements->finalize($period, $request->user());

        return redirect()->route('settlements.index', ['period' => $period->id])
            ->with('status', "Settlement finalized (version {$snapshot->calculation_version}); member charges posted and the ledger reconciled.");
    }

    /**
     * Member statement: visible to finance users and to the member themself.
     */
    public function show(Request $request, Tenant $tenant, Mess $mess, Settlement $settlement): View
    {
        abort_unless($request->user()->can('viewFinance', $mess) || $settlement->member->user_id === $request->user()->id, 403);

        return view('settlements.show', [
            'mess' => $mess,
            'settlement' => $settlement->load('items', 'payments.receiver', 'member', 'period', 'snapshot'),
            'canPay' => $request->user()->can('paySettlements', $mess),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }
}
