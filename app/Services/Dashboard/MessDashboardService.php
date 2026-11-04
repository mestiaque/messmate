<?php

namespace App\Services\Dashboard;

use App\Enums\DepositStatus;
use App\Enums\EntryStatus;
use App\Enums\MembershipStatus;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Services\Finance\FinanceSetupService;
use App\Services\Settlement\SettlementService;
use App\Support\Decimal;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin dashboard figures for one period, all from database values. Cached
 * briefly under a tenant- and mess-scoped key.
 */
class MessDashboardService
{
    public const CACHE_SECONDS = 60;

    public function __construct(
        private readonly SettlementService $settlements,
        private readonly FinanceSetupService $setup,
        private readonly TenantContext $context,
    ) {}

    public function summary(Mess $mess, AccountingPeriod $period): array
    {
        return Cache::remember($this->context->cacheKey("dashboard:period:{$period->id}"), self::CACHE_SECONDS, fn () => $this->build($mess, $period));
    }

    private function build(Mess $mess, AccountingPeriod $period): array
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $preview = $this->settlements->calculate($period);

        $units = $period->mealEntries()->whereIn('status', [EntryStatus::Submitted, EntryStatus::Approved]);
        $approvedBazar = $period->bazarEntries()->where('status', EntryStatus::Approved);
        $approvedExpenses = $period->expenses()->where('status', EntryStatus::Approved);

        $memberCredit = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);
        $balances = DB::table('ledger_lines')->where('account_id', $memberCredit->id)->whereNotNull('member_id')
            ->groupBy('member_id')->selectRaw('member_id, SUM(credit) - SUM(debit) AS balance')->pluck('balance');

        // One point per day of the period, zero-filled.
        $days = [];
        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $d->addDay()) {
            $days[$d->toDateString()] = ['units' => '0', 'spend' => '0.00'];
        }
        foreach ((clone $units)->groupBy('meal_date')->selectRaw('meal_date, SUM(total_units) AS u')->get() as $row) {
            $days[Carbon::parse($row->meal_date)->toDateString()]['units'] = Decimal::of($row->u);
        }
        foreach ((clone $approvedBazar)->groupBy('purchase_date')->selectRaw('purchase_date AS d, SUM(total_amount) AS a')->get()
            ->concat((clone $approvedExpenses)->groupBy('expense_date')->selectRaw('expense_date AS d, SUM(amount) AS a')->get()) as $row) {
            $key = Carbon::parse($row->d)->toDateString();
            $days[$key]['spend'] = bcadd($days[$key]['spend'], Decimal::of($row->a, 2), 2);
        }

        return [
            'active_members' => $mess->members()->where('membership_status', MembershipStatus::Active)->count(),
            'total_units' => Decimal::of((clone $units)->sum('total_units')),
            'estimated_rate' => $preview['meal_rate'],
            'rate_problem' => $preview['errors'][0] ?? null,
            'bazar_total' => Decimal::of((clone $approvedBazar)->sum('total_amount'), 2),
            'other_expenses' => Decimal::of((clone $approvedExpenses)->sum('amount'), 2),
            'deposits' => Decimal::of($period->deposits()->where('status', DepositStatus::Approved)->sum('amount'), 2),
            // What members owe right now: the sum of negative member balances.
            'outstanding' => ltrim(Decimal::sum($balances->filter(fn ($b) => $b < 0)->all(), 2), '-'),
            'pending' => [
                'Meals' => $period->mealEntries()->where('status', EntryStatus::Submitted)->count() + $period->guestMeals()->where('status', EntryStatus::Submitted)->count() + $period->mealAdjustments()->where('status', EntryStatus::Submitted)->count(),
                'Bazar' => $period->bazarEntries()->where('status', EntryStatus::Submitted)->count(),
                'Expenses' => $period->expenses()->where('status', EntryStatus::Submitted)->count(),
                'Deposits' => $period->deposits()->where('status', DepositStatus::Pending)->count(),
            ],
            'recent' => $mess->ledgerTransactions()->with('lines:id,transaction_id,debit')->latest('id')->limit(6)->get()
                ->map(fn ($tx) => ['number' => $tx->transaction_number, 'date' => $tx->transaction_date->toDateString(), 'description' => $tx->description, 'amount' => Decimal::sum($tx->lines->pluck('debit'), 2), 'status' => $tx->status])->all(),
            'trend' => $this->trend($days, now($mess->timezone)->toDateString()),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Days after today have no data yet: send null so the chart shows a gap
     * instead of a misleading drop to zero.
     */
    private function trend(array $days, string $today): array
    {
        $future = fn ($date) => $date > $today;

        return [
            'labels' => array_keys($days),
            'units' => array_map(fn ($date, $d) => $future($date) ? null : $d['units'], array_keys($days), $days),
            'spend' => array_map(fn ($date, $d) => $future($date) ? null : $d['spend'], array_keys($days), $days),
        ];
    }
}
