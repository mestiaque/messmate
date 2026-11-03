<?php

namespace App\Http\Controllers\Finance;

use App\Enums\DepositStatus;
use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Period summary of approved bazar, expenses and deposits (operational report;
 * the settlement calculation arrives in Phase 4).
 */
class FinanceReportController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, Mess $mess, AccountingPeriodService $periods): View
    {
        $this->authorize('viewFinance', $mess);

        $period = $request->filled('period')
            ? $mess->periods()->findOrFail($request->integer('period'))
            : ($periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first());

        $data = ['mess' => $mess, 'period' => $period, 'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name'])];

        if (! $period) {
            return view('finance.report', $data);
        }

        $approvedBazar = $period->bazarEntries()->where('status', EntryStatus::Approved);

        // Itemized purchases by item category; total-mode purchases by their single category.
        $bazarByCategory = DB::query()->fromSub(
            DB::table('bazar_items')->join('bazar_entries', 'bazar_entries.id', '=', 'bazar_items.bazar_entry_id')
                ->where('bazar_entries.period_id', $period->id)->where('bazar_entries.status', 'approved')
                ->selectRaw('bazar_items.category_id, bazar_items.total AS amount')
                ->unionAll(
                    DB::table('bazar_entries')->where('period_id', $period->id)->where('status', 'approved')->where('entry_mode', 'total')
                        ->selectRaw('category_id, subtotal AS amount')
                ), 'x')
            ->join('bazar_categories', 'bazar_categories.id', '=', 'x.category_id')
            ->groupBy('bazar_categories.name', 'bazar_categories.is_food_related')
            ->selectRaw('bazar_categories.name, bazar_categories.is_food_related, SUM(x.amount) AS amount')
            ->orderByDesc('amount')->get();

        return view('finance.report', $data + [
            'bazarTotal' => Decimal::of((clone $approvedBazar)->sum('total_amount'), 2),
            'bazarFood' => Decimal::of((clone $approvedBazar)->sum('food_amount'), 2),
            'bazarDiscounts' => Decimal::of((clone $approvedBazar)->sum('discount'), 2),
            'bazarByCategory' => $bazarByCategory,
            'bazarByPurchaser' => (clone $approvedBazar)->join('mess_members', 'mess_members.id', '=', 'bazar_entries.purchased_by')
                ->groupBy('mess_members.full_name')->selectRaw('mess_members.full_name, COUNT(*) AS trips, SUM(total_amount) AS amount')->orderByDesc('amount')->get(),
            'expensesByCategory' => $period->expenses()->where('expenses.status', EntryStatus::Approved)
                ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
                ->groupBy('expense_categories.name', 'expense_categories.category_type')
                ->selectRaw('expense_categories.name, expense_categories.category_type, SUM(amount) AS amount')->orderByDesc('amount')->get(),
            'depositsByMember' => $period->deposits()->where('deposits.status', DepositStatus::Approved)
                ->join('mess_members', 'mess_members.id', '=', 'deposits.member_id')
                ->groupBy('mess_members.member_code', 'mess_members.full_name')
                ->selectRaw('mess_members.member_code, mess_members.full_name, COUNT(*) AS count, SUM(amount) AS amount')->orderBy('mess_members.member_code')->get(),
            'pending' => [
                'bazar' => $period->bazarEntries()->where('status', EntryStatus::Submitted)->count(),
                'expenses' => $period->expenses()->where('status', EntryStatus::Submitted)->count(),
                'deposits' => $period->deposits()->where('status', DepositStatus::Pending)->count(),
            ],
        ]);
    }
}
