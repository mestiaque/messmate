<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AllocationMethod;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Models\Expense;
use App\Models\LedgerTransaction;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\IdempotencyService;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly ReceiptStorage $receipts,
    ) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $this->authorizeAny($request, $mess);

        return view('finance.expenses.index', [
            'mess' => $mess,
            'expenses' => $mess->expenses()
                ->with('category:id,name,category_type', 'paidBy:id,full_name', 'period:id,name,status')
                ->when($request->query('period'), fn ($q, $p) => $q->where('period_id', $p))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('expense_date')->orderByDesc('id')
                ->paginate(30)->withQueryString(),
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'canManage' => $request->user()->can('manageExpenses', $mess),
            'canApprove' => $request->user()->can('approveExpenses', $mess),
        ]);
    }

    public function create(Tenant $tenant, Mess $mess, FinanceSetupService $setup): View
    {
        $this->authorize('manageExpenses', $mess);
        $setup->ensureDefaults($mess);

        return view('finance.expenses.form', $this->formData($mess, new Expense([
            'expense_date' => now($mess->timezone), 'allocation_method' => AllocationMethod::Equal, 'payment_method' => PaymentMethod::Cash,
        ])));
    }

    public function store(ExpenseRequest $request, Tenant $tenant, Mess $mess, IdempotencyService $idempotency): RedirectResponse
    {
        [$fresh, $expense] = $idempotency->once($mess->tenant_id, $request->input('idempotency_key'), $request->safe()->except('receipt'), fn () => $this->expenses->save(
            $mess, $request->safe()->except(['receipt', 'idempotency_key']), $request->user(), receiptPath: $this->receipts->store($mess, $request->file('receipt')),
        ));

        if (! $fresh) {
            return redirect()->route('expenses.index')->with('status', 'That expense was already saved.');
        }

        return redirect()->route('expenses.show', ['expense' => $expense])->with('status', 'Expense recorded.');
    }

    public function show(Request $request, Tenant $tenant, Mess $mess, Expense $expense): View
    {
        $this->authorizeAny($request, $mess);

        return view('finance.expenses.show', [
            'mess' => $mess,
            'expense' => $expense->load('category', 'paidBy', 'period', 'allocations.member', 'creator'),
            'ledger' => $expense->ledger_transaction_id ? LedgerTransaction::with('lines.account', 'reversal')->find($expense->ledger_transaction_id) : null,
            'canManage' => $request->user()->can('manageExpenses', $mess),
            'canApprove' => $request->user()->can('approveExpenses', $mess),
        ]);
    }

    public function edit(Tenant $tenant, Mess $mess, Expense $expense): View|RedirectResponse
    {
        $this->authorize('manageExpenses', $mess);

        if (! $expense->isEditable()) {
            return redirect()->route('expenses.show', ['expense' => $expense])->withErrors(['status' => 'Approved or voided expenses cannot be edited.']);
        }

        return view('finance.expenses.form', $this->formData($mess, $expense->load('allocations')));
    }

    public function update(ExpenseRequest $request, Tenant $tenant, Mess $mess, Expense $expense): RedirectResponse
    {
        $this->expenses->save($mess, $request->safe()->except(['receipt', 'idempotency_key']), $request->user(), $expense, $this->receipts->store($mess, $request->file('receipt')));

        return redirect()->route('expenses.show', ['expense' => $expense])->with('status', 'Expense updated and resubmitted.');
    }

    public function decide(Request $request, Tenant $tenant, Mess $mess, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'void'])],
            'reason' => ['required_unless:action,approve', 'nullable', 'string', 'max:255'],
        ]);
        $this->authorize($data['action'] === 'void' ? 'manageExpenses' : 'approveExpenses', $mess);

        match ($data['action']) {
            'approve' => $this->expenses->approve($expense, $request->user()),
            'reject' => $this->expenses->reject($expense, $request->user(), $data['reason']),
            'void' => $this->expenses->void($expense, $request->user(), $data['reason']),
        };

        return back()->with('status', 'Expense '.['approve' => 'approved and posted to the ledger', 'reject' => 'rejected', 'void' => 'voided'][$data['action']].'.');
    }

    private function formData(Mess $mess, Expense $expense): array
    {
        return [
            'mess' => $mess,
            'expense' => $expense,
            'categories' => $mess->expenseCategories()->where('is_active', true)->orderBy('name')->get(),
            'members' => $mess->members()->whereIn('membership_status', ['active', 'left'])->orderBy('member_code')->get(['id', 'member_code', 'full_name']),
            'idempotencyKey' => (string) Str::uuid(),
        ];
    }

    private function authorizeAny(Request $request, Mess $mess): void
    {
        $user = $request->user();
        abort_unless($user->can('manageExpenses', $mess) || $user->can('approveExpenses', $mess) || $user->can('viewFinance', $mess), 403);
    }
}
