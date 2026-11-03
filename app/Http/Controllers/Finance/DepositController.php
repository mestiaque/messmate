<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\DepositRequest;
use App\Models\Deposit;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Finance\DepositService;
use App\Services\Finance\IdempotencyService;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function __construct(private readonly DepositService $deposits) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $user = $request->user();
        abort_unless($user->can('manageDeposits', $mess) || $user->can('approveDeposits', $mess) || $user->can('viewFinance', $mess), 403);

        return view('finance.deposits.index', [
            'mess' => $mess,
            'deposits' => $mess->deposits()
                ->with('member:id,member_code,full_name', 'period:id,name,status', 'receiver:id,name')
                ->when($request->query('period'), fn ($q, $p) => $q->where('period_id', $p))
                ->when($request->query('member'), fn ($q, $m) => $q->where('member_id', $m))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('paid_at')->orderByDesc('id')
                ->paginate(30)->withQueryString(),
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'members' => $mess->members()->whereIn('membership_status', ['active', 'left', 'inactive', 'suspended'])->orderBy('member_code')->get(['id', 'member_code', 'full_name']),
            'canManage' => $user->can('manageDeposits', $mess),
            'canApprove' => $user->can('approveDeposits', $mess),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(DepositRequest $request, Tenant $tenant, Mess $mess, IdempotencyService $idempotency, ReceiptStorage $receipts): RedirectResponse
    {
        $approve = $request->boolean('approve') && $request->user()->can('approveDeposits', $mess);

        [$fresh] = $idempotency->once($mess->tenant_id, $request->input('idempotency_key'), $request->safe()->except('receipt'), fn () => DB::transaction(function () use ($request, $mess, $receipts, $approve) {
            $deposit = $this->deposits->record($mess, $request->safe()->except(['receipt', 'approve', 'idempotency_key']), $request->user(), $receipts->store($mess, $request->file('receipt')));

            return $approve ? $this->deposits->approve($deposit, $request->user()) : $deposit;
        }));

        return back()->with('status', $fresh ? ($approve ? 'Deposit recorded and posted.' : 'Deposit recorded; awaiting approval.') : 'That deposit was already saved.');
    }

    public function decide(Request $request, Tenant $tenant, Mess $mess, Deposit $deposit): RedirectResponse
    {
        $this->authorize('approveDeposits', $mess);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'reverse'])],
            'reason' => ['required_unless:action,approve', 'nullable', 'string', 'max:255'],
        ]);

        match ($data['action']) {
            'approve' => $this->deposits->approve($deposit, $request->user()),
            'reject' => $this->deposits->reject($deposit, $request->user(), $data['reason']),
            'reverse' => $this->deposits->reverse($deposit, $request->user(), $data['reason']),
        };

        return back()->with('status', 'Deposit '.['approve' => 'approved and posted', 'reject' => 'rejected', 'reverse' => 'reversed'][$data['action']].'.');
    }
}
