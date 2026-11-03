<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $this->authorize('viewFinance', $mess);

        return view('finance.ledger.index', [
            'mess' => $mess,
            'transactions' => $mess->ledgerTransactions()
                ->with('lines.account:id,name', 'lines.member:id,full_name', 'creator:id,name')
                ->when($request->query('account'), fn ($q, $a) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $a)))
                ->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'accounts' => $mess->financialAccounts()->orderBy('id')->get(),
        ]);
    }

    /**
     * Account balances (trial balance) and each member's running credit.
     */
    public function balances(Tenant $tenant, Mess $mess, FinanceSetupService $setup): View
    {
        $this->authorize('viewFinance', $mess);
        $setup->ensureDefaults($mess);

        $accounts = $mess->financialAccounts()->orderBy('id')->get()
            ->map(fn ($a) => ['account' => $a, 'balance' => $this->ledger->accountBalance($a)]);

        $memberCredit = $setup->account($mess, FinancialAccount::MEMBER_CREDIT);
        $members = DB::table('mess_members')
            ->leftJoin('ledger_lines', fn ($j) => $j->on('ledger_lines.member_id', '=', 'mess_members.id')->where('ledger_lines.account_id', $memberCredit->id))
            ->where('mess_members.mess_id', $mess->id)
            ->whereNull('mess_members.deleted_at')
            ->groupBy('mess_members.id', 'mess_members.member_code', 'mess_members.full_name')
            ->orderBy('mess_members.member_code')
            ->selectRaw('mess_members.id, mess_members.member_code, mess_members.full_name, COALESCE(SUM(ledger_lines.credit), 0) - COALESCE(SUM(ledger_lines.debit), 0) AS balance')
            ->get();

        return view('finance.ledger.balances', [
            'mess' => $mess,
            'accounts' => $accounts,
            'members' => $members,
            'totals' => $this->ledger->totals($mess),
        ]);
    }
}
