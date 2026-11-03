<?php

namespace App\Http\Controllers\Settlement;

use App\Http\Controllers\Controller;
use App\Models\FinancialAdjustment;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Settlement\FinancialAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialAdjustmentController extends Controller
{
    public function __construct(private readonly FinancialAdjustmentService $adjustments) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        abort_unless($request->user()->can('manageAdjustments', $mess) || $request->user()->can('viewFinance', $mess), 403);

        return view('settlements.adjustments', [
            'mess' => $mess,
            'adjustments' => $mess->financialAdjustments()->with('member:id,member_code,full_name', 'period:id,name,status', 'creator:id,name')->latest()->paginate(30),
            'periods' => $mess->periods()->where('status', 'open')->orderByDesc('start_date')->get(['id', 'name']),
            'members' => $mess->members()->where('membership_status', '!=', 'invited')->orderBy('member_code')->get(['id', 'member_code', 'full_name']),
            'canManage' => $request->user()->can('manageAdjustments', $mess),
        ]);
    }

    public function store(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('manageAdjustments', $mess);
        $data = $request->validate([
            'period_id' => ['required', 'integer'],
            'member_id' => ['required', 'integer'],
            'adjustment_type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:255'],
            'approve' => ['boolean'],
        ]);

        $this->adjustments->create(
            $mess->periods()->findOrFail($data['period_id']),
            $mess->members()->findOrFail($data['member_id']),
            $data['adjustment_type'],
            (string) $data['amount'],
            $data['reason'],
            $request->user(),
            $request->boolean('approve'),
        );

        return back()->with('status', 'Adjustment recorded.');
    }

    public function decide(Request $request, Tenant $tenant, Mess $mess, FinancialAdjustment $financialAdjustment): RedirectResponse
    {
        $this->authorize('manageAdjustments', $mess);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'void'])],
            'reason' => ['required_unless:action,approve', 'nullable', 'string', 'max:255'],
        ]);

        match ($data['action']) {
            'approve' => $this->adjustments->approve($financialAdjustment, $request->user()),
            'reject' => $this->adjustments->reject($financialAdjustment, $request->user(), $data['reason']),
            'void' => $this->adjustments->void($financialAdjustment, $request->user(), $data['reason']),
        };

        return back()->with('status', 'Adjustment '.['approve' => 'approved and posted', 'reject' => 'rejected', 'void' => 'voided'][$data['action']].'.');
    }
}
