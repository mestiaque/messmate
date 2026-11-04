<?php

namespace App\Services\Reports;

use App\Enums\EntryStatus;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\MonthlySnapshot;
use App\Services\Finance\FinanceSetupService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds tabular reports from database values. Every report has the same shape
 * so one HTML view and one CSV writer serve them all.
 *
 * Shape: ['title', 'subtitle', 'columns' => [key => label], 'numeric' => [keys], 'rows' => list<array>, 'totals' => array]
 */
class ReportService
{
    /** type => [label, needs (period|date|member|none), permission ability] */
    public const TYPES = [
        'daily-meals' => ['Daily meals', 'date', 'viewMeals'],
        'monthly-meals' => ['Monthly meals by member', 'period', 'viewMeals'],
        'bazar' => ['Bazar purchases', 'period', 'viewFinance'],
        'expenses' => ['Expenses', 'period', 'viewFinance'],
        'deposits' => ['Deposits', 'period', 'viewFinance'],
        'settlement' => ['Settlement', 'period', 'viewFinance'],
        'outstanding' => ['Outstanding balances', 'none', 'viewFinance'],
        'member-ledger' => ['Member ledger', 'member', 'viewFinance'],
    ];

    public function __construct(private readonly FinanceSetupService $setup) {}

    public function build(string $type, Mess $mess, ?AccountingPeriod $period, ?string $date, ?MessMember $member): array
    {
        return match ($type) {
            'daily-meals' => $this->dailyMeals($mess, $date ?? now($mess->timezone)->toDateString()),
            'monthly-meals' => $this->monthlyMeals($mess, $period),
            'bazar' => $this->bazar($period),
            'expenses' => $this->expenses($period),
            'deposits' => $this->deposits($period),
            'settlement' => $this->settlement($period),
            'outstanding' => $this->outstanding($mess),
            'member-ledger' => $this->memberLedger($mess, $member),
        };
    }

    private function dailyMeals(Mess $mess, string $date): array
    {
        $types = $mess->mealTypes()->orderBy('sort_order')->get();
        $entries = $mess->mealEntries()->whereDate('meal_date', $date)->whereIn('status', [EntryStatus::Submitted, EntryStatus::Approved])
            ->with('member:id,member_code,full_name', 'items')->get()->sortBy('member.member_code');

        $columns = ['code' => 'Code', 'member' => 'Member'];
        foreach ($types as $t) {
            $columns["t{$t->id}"] = $t->name;
        }
        $columns += ['units' => 'Units', 'status' => 'Status'];

        $rows = $entries->map(function ($e) use ($types) {
            $row = ['code' => $e->member->member_code, 'member' => $e->member->full_name];
            $qty = $e->items->pluck('quantity', 'meal_type_id');
            foreach ($types as $t) {
                $row["t{$t->id}"] = $e->tracking_mode->value === 'individual' ? Decimal::of($qty[$t->id] ?? '0') : '';
            }

            return $row + ['units' => Decimal::of($e->total_units), 'status' => $e->status->value];
        })->values()->all();

        return $this->report('Daily meals', Carbon::parse($date)->format('l, d M Y'), $columns, $rows, ['units', ...array_map(fn ($t) => "t{$t->id}", $types->all())]);
    }

    private function monthlyMeals(Mess $mess, AccountingPeriod $period): array
    {
        $types = $mess->mealTypes()->orderBy('sort_order')->get();
        $perType = DB::table('meal_entry_items')->join('meal_entries', 'meal_entries.id', '=', 'meal_entry_items.meal_entry_id')
            ->where('meal_entries.period_id', $period->id)->where('meal_entries.status', 'approved')
            ->groupBy('meal_entries.member_id', 'meal_entry_items.meal_type_id')
            ->selectRaw('meal_entries.member_id, meal_entry_items.meal_type_id, SUM(quantity) AS q')->get()->groupBy('member_id');
        $totals = $period->mealEntries()->groupBy('member_id')
            ->selectRaw("member_id, SUM(CASE WHEN status = 'approved' THEN total_units ELSE 0 END) AS approved, SUM(CASE WHEN status = 'submitted' THEN total_units ELSE 0 END) AS pending, SUM(status IN ('approved', 'submitted')) AS days")
            ->get()->keyBy('member_id');
        $adjustments = $period->mealAdjustments()->where('status', EntryStatus::Approved)->groupBy('member_id')->selectRaw('member_id, SUM(adjustment_units) AS u')->pluck('u', 'member_id');

        $columns = ['code' => 'Code', 'member' => 'Member', 'days' => 'Days'];
        foreach ($types as $t) {
            $columns["t{$t->id}"] = $t->name;
        }
        $columns += ['approved' => 'Approved units', 'adjustments' => 'Adjustments', 'pending' => 'Pending units'];

        $rows = [];
        foreach ($mess->members()->whereIn('id', $totals->keys()->merge($adjustments->keys()))->orderBy('member_code')->get() as $m) {
            $row = ['code' => $m->member_code, 'member' => $m->full_name, 'days' => (string) (int) ($totals[$m->id]->days ?? 0)];
            $q = collect($perType[$m->id] ?? [])->pluck('q', 'meal_type_id');
            foreach ($types as $t) {
                $row["t{$t->id}"] = Decimal::of($q[$t->id] ?? '0');
            }
            $rows[] = $row + [
                'approved' => Decimal::of($totals[$m->id]->approved ?? '0'),
                'adjustments' => Decimal::of($adjustments[$m->id] ?? '0'),
                'pending' => Decimal::of($totals[$m->id]->pending ?? '0'),
            ];
        }

        return $this->report('Monthly meals', $period->name, $columns, $rows, ['days', 'approved', 'adjustments', 'pending', ...array_map(fn ($t) => "t{$t->id}", $types->all())]);
    }

    private function bazar(AccountingPeriod $period): array
    {
        $rows = $period->bazarEntries()->with('purchaser:id,full_name', 'category:id,name')->orderBy('purchase_date')->orderBy('id')->get()
            ->map(fn ($b) => [
                'date' => $b->purchase_date->toDateString(), 'id' => '#'.$b->id, 'purchaser' => $b->purchaser->full_name,
                'description' => $b->description ?: ($b->entry_mode === 'total' ? $b->category?->name : 'Itemized'),
                'paid_by' => $b->payment_method->label(), 'total' => Decimal::of($b->total_amount, 2), 'food' => Decimal::of($b->food_amount, 2), 'status' => $b->status->value,
            ])->all();

        return $this->report('Bazar purchases', $period->name, ['date' => 'Date', 'id' => 'No.', 'purchaser' => 'Purchaser', 'description' => 'Description', 'paid_by' => 'Paid with', 'total' => 'Total', 'food' => 'Food share', 'status' => 'Status'], $rows, ['total', 'food'], approvedOnlyTotals: true);
    }

    private function expenses(AccountingPeriod $period): array
    {
        $rows = $period->expenses()->with('category:id,name', 'paidBy:id,full_name')->orderBy('expense_date')->orderBy('id')->get()
            ->map(fn ($e) => [
                'date' => $e->expense_date->toDateString(), 'title' => $e->title, 'category' => $e->category->name,
                'split' => str_replace('_', ' ', $e->allocation_method->value), 'paid_by' => $e->payment_method->label().($e->paidBy ? " ({$e->paidBy->full_name})" : ''),
                'amount' => Decimal::of($e->amount, 2), 'status' => $e->status->value,
            ])->all();

        return $this->report('Expenses', $period->name, ['date' => 'Date', 'title' => 'Title', 'category' => 'Category', 'split' => 'Split', 'paid_by' => 'Paid with', 'amount' => 'Amount', 'status' => 'Status'], $rows, ['amount'], approvedOnlyTotals: true);
    }

    private function deposits(AccountingPeriod $period): array
    {
        $rows = $period->deposits()->with('member:id,member_code,full_name')->orderBy('paid_at')->orderBy('id')->get()
            ->map(fn ($d) => [
                'date' => $d->paid_at->toDateString(), 'code' => $d->member->member_code, 'member' => $d->member->full_name,
                'method' => $d->payment_method->label(), 'reference' => (string) $d->transaction_reference,
                'amount' => Decimal::of($d->amount, 2), 'status' => $d->status->value,
            ])->all();

        return $this->report('Deposits', $period->name, ['date' => 'Date', 'code' => 'Code', 'member' => 'Member', 'method' => 'Method', 'reference' => 'Reference', 'amount' => 'Amount', 'status' => 'Status'], $rows, ['amount'], approvedOnlyTotals: true);
    }

    private function settlement(AccountingPeriod $period): array
    {
        $snapshot = $period->snapshots()->where('status', MonthlySnapshot::FINAL)->first();
        $rows = $snapshot ? $snapshot->settlements()->with('member:id,member_code,full_name')->get()->sortBy('member.member_code')
            ->map(fn ($s) => [
                'code' => $s->member->member_code, 'member' => $s->member->full_name, 'opening' => Decimal::of($s->opening_credit, 2),
                'units' => Decimal::of($s->meal_units), 'meals' => Decimal::of(bcadd($s->meal_cost, $s->guest_cost, 2), 2), 'other' => Decimal::of($s->other_expense, 2),
                'deposits' => Decimal::of($s->total_deposits, 2), 'adjust' => bcsub($s->other_credits, $s->other_debits, 2),
                'closing' => Decimal::of($s->closing_credit, 2), 'paid' => Decimal::of($s->paid_amount, 2), 'status' => str_replace('_', ' ', $s->status),
            ])->values()->all() : [];

        $subtitle = $snapshot ? "{$period->name} · version {$snapshot->calculation_version} · rate ".number_format((float) $snapshot->calculated_meal_rate, 4) : "{$period->name} · not finalized";

        return $this->report('Settlement', $subtitle, ['code' => 'Code', 'member' => 'Member', 'opening' => 'Opening', 'units' => 'Units', 'meals' => 'Meals & guests', 'other' => 'Other', 'deposits' => 'Deposits', 'adjust' => '± Other', 'closing' => 'Closing', 'paid' => 'Paid', 'status' => 'Status'],
            $rows, ['opening', 'units', 'meals', 'other', 'deposits', 'adjust', 'closing', 'paid']);
    }

    private function outstanding(Mess $mess): array
    {
        $account = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);
        $rows = DB::table('ledger_lines')->join('mess_members', 'mess_members.id', '=', 'ledger_lines.member_id')
            ->where('ledger_lines.account_id', $account->id)
            ->groupBy('mess_members.id', 'mess_members.member_code', 'mess_members.full_name', 'mess_members.membership_status')
            ->havingRaw('SUM(ledger_lines.credit) - SUM(ledger_lines.debit) <> 0')
            ->orderBy('mess_members.member_code')
            ->selectRaw('mess_members.member_code AS code, mess_members.full_name AS member, mess_members.membership_status AS status, SUM(ledger_lines.credit) - SUM(ledger_lines.debit) AS balance')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code, 'member' => $r->member, 'status' => $r->status,
                'owes' => Decimal::compare($r->balance, '0') < 0 ? ltrim(Decimal::of($r->balance, 2), '-') : '0.00',
                'owed' => Decimal::compare($r->balance, '0') > 0 ? Decimal::of($r->balance, 2) : '0.00',
            ])->all();

        return $this->report('Outstanding balances', 'As of '.now($mess->timezone)->format('d M Y H:i'), ['code' => 'Code', 'member' => 'Member', 'status' => 'Membership', 'owes' => 'Member owes mess', 'owed' => 'Mess owes member'], $rows, ['owes', 'owed']);
    }

    private function memberLedger(Mess $mess, MessMember $member): array
    {
        $account = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);
        $running = '0.00';
        $rows = $account->lines()->where('member_id', $member->id)
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_lines.transaction_id')
            ->orderBy('ledger_transactions.transaction_date')->orderBy('ledger_transactions.id')
            ->get(['ledger_transactions.transaction_date', 'ledger_transactions.transaction_number', 'ledger_transactions.description', 'ledger_lines.debit', 'ledger_lines.credit'])
            ->map(function ($l) use (&$running) {
                $running = bcadd($running, bcsub($l->credit, $l->debit, 2), 2);

                return [
                    'date' => Carbon::parse($l->transaction_date)->toDateString(), 'number' => $l->transaction_number, 'description' => $l->description,
                    'charge' => Decimal::compare($l->debit, '0') > 0 ? Decimal::of($l->debit, 2) : '', 'credit' => Decimal::compare($l->credit, '0') > 0 ? Decimal::of($l->credit, 2) : '',
                    'balance' => $running,
                ];
            })->all();

        return $this->report("Member ledger: {$member->full_name}", "{$member->member_code} · positive balance = the mess owes the member", ['date' => 'Date', 'number' => 'No.', 'description' => 'Description', 'charge' => 'Charge', 'credit' => 'Credit', 'balance' => 'Balance'], $rows, ['charge', 'credit']);
    }

    /**
     * @param  list<string>  $numeric  columns to right-align and total
     */
    private function report(string $title, string $subtitle, array $columns, array $rows, array $numeric, bool $approvedOnlyTotals = false): array
    {
        $totalRows = $approvedOnlyTotals ? array_filter($rows, fn ($r) => $r['status'] === 'approved') : $rows;
        $totals = [];
        foreach ($numeric as $key) {
            $values = array_filter(array_column($totalRows, $key), fn ($v) => $v !== '');
            // Total at the precision the column's values use (money 2, units 4, counts 0).
            $scale = max([0, ...array_map(fn ($v) => strlen(strrchr((string) $v, '.') ?: '.') - 1, array_column($rows, $key))]);
            $totals[$key] = Decimal::sum($values, $scale);
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'columns' => $columns,
            'numeric' => $numeric,
            'rows' => $rows,
            'totals' => $totals,
            'totals_note' => $approvedOnlyTotals ? 'Totals include approved records only.' : null,
        ];
    }
}
