<?php

namespace App\Services\Settlement;

use App\Enums\DepositStatus;
use App\Enums\MembershipStatus;
use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\LedgerTransaction;
use App\Models\Mess;
use App\Models\MonthlySnapshot;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Settlement\Strategies\MealRateInput;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly settlement: calculate (pure), save a draft, finalize (immutable
 * snapshot + ledger charges + reconciliation + period finalized), and supersede
 * on an authorized reopen.
 */
class SettlementService
{
    public const CALCULATION_ENGINE = 'v1';

    public function __construct(
        private readonly FoodExpensePoolService $pool,
        private readonly MealCalculationService $meals,
        private readonly MealRateCalculator $rates,
        private readonly ExpenseAllocationService $allocations,
        private readonly MemberBalanceService $balances,
        private readonly FinancialReconciliationService $reconciliation,
        private readonly SettlementSettingsService $settings,
        private readonly FinanceSetupService $setup,
        private readonly LedgerService $ledger,
        private readonly AccountingPeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Full calculation for a period without writing anything.
     */
    public function calculate(AccountingPeriod $period): array
    {
        $mess = Mess::findOrFail($period->mess_id);
        $policy = $this->settings->get($mess);
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();

        $food = $this->pool->calculate($period);
        $units = $this->meals->units($period);
        $memberCredit = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);

        // Eligibility by effective membership dates, not the current status alone.
        $eligible = [];
        $members = $mess->members()->where('membership_status', '!=', MembershipStatus::Invited)->orderBy('member_code')->get();
        foreach ($members as $m) {
            $from = max($start, $m->joined_at->toDateString());
            $to = min($end, $m->left_at?->toDateString() ?? $end);
            if ($from <= $to) {
                $eligible[$m->id] = ['days' => Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1];
            }
        }

        $memberUnits = array_map(fn ($u) => $u['total'], $units['members']);
        $strategy = $this->rates->strategy($policy);
        $input = new MealRateInput($food['total'], $memberUnits, $units['sponsored_guests'], $units['unsponsored_guests'], array_keys($eligible), $policy->customRate);
        $errors = $strategy->validate($input);
        $rate = $errors ? null : $strategy->calculate($input);

        // Food cost of guests nobody sponsored is shared equally by eligible members, as its own line.
        $unsponsoredShares = [];
        if ($rate && Decimal::compare($rate->unsponsoredGuestCost, '0') > 0) {
            $eligible ? $unsponsoredShares = Decimal::allocate($rate->unsponsoredGuestCost, array_fill_keys(array_keys($eligible), '1'))
                : $errors[] = 'Unsponsored guest meals have no eligible members to share them.';
        }

        $other = $this->allocations->allocate($period, $eligible, $memberUnits, $policy, $food['bazar_non_food']);
        $errors = [...$errors, ...$other['errors']];

        $rows = [];
        foreach ($members as $m) {
            $movement = $this->balances->movements($period, $m, $memberCredit);
            $mealUnits = $memberUnits[$m->id] ?? '0.0000';
            $mealCost = $rate?->memberCost[$m->id] ?? '0.00';
            $guestCost = $rate?->guestCost[$m->id] ?? '0.00';
            $guestShare = $unsponsoredShares[$m->id] ?? '0.00';
            $otherShares = $other['shares'][$m->id] ?? [];
            $otherTotal = Decimal::sum(array_column($otherShares, 'amount'), 2);

            $isRelevant = isset($eligible[$m->id]) || ! Decimal::isZero($movement['opening']) || $movement['items'] || ! Decimal::isZero($mealUnits);
            if (! $isRelevant) {
                continue;
            }

            $payable = Decimal::sum([$mealCost, $guestCost, $guestShare, $otherTotal], 2);
            $closing = bcsub(Decimal::sum([$movement['opening'], $movement['deposits'], $movement['credits']], 2), bcadd($payable, $movement['debits'], 2), 2);

            $items = [];
            if (! Decimal::isZero($mealUnits) || ! Decimal::isZero($mealCost)) {
                $items[] = ['type' => 'meal', 'description' => 'Meals', 'quantity' => $mealUnits, 'unit_rate' => $rate?->rate, 'amount' => '-'.$mealCost, 'ref' => $units['members'][$m->id] ?? null];
            }
            if (! Decimal::isZero($guestCost)) {
                $items[] = ['type' => 'guest', 'description' => 'Sponsored guest meals', 'quantity' => $units['sponsored_guests'][$m->id] ?? null, 'unit_rate' => $rate?->rate, 'amount' => '-'.$guestCost, 'ref' => null];
            }
            if (! Decimal::isZero($guestShare)) {
                $items[] = ['type' => 'guest_share', 'description' => 'Share of unsponsored guest meals', 'quantity' => null, 'unit_rate' => null, 'amount' => '-'.$guestShare, 'ref' => null];
            }
            foreach ($otherShares as $share) {
                $items[] = ['type' => 'expense', 'description' => $share['title'].' ('.str_replace('_', ' ', $share['method']).')', 'quantity' => null, 'unit_rate' => null, 'amount' => '-'.$share['amount'], 'ref' => ['expense_id' => $share['expense_id']]];
            }
            foreach ($movement['items'] as $mv) {
                $items[] = ['type' => $mv['type'], 'description' => $mv['description'], 'quantity' => null, 'unit_rate' => null, 'amount' => $mv['amount'], 'ref' => $mv['ref']];
            }

            $rows[$m->id] = [
                'name' => $m->full_name,
                'code' => $m->member_code,
                'eligible' => isset($eligible[$m->id]),
                'days' => $eligible[$m->id]['days'] ?? 0,
                'opening_credit' => $movement['opening'],
                'meal_units' => $mealUnits,
                'meal_cost' => $mealCost,
                'guest_cost' => bcadd($guestCost, $guestShare, 2),
                'other_expense' => $otherTotal,
                'total_deposits' => $movement['deposits'],
                'other_credits' => $movement['credits'],
                'other_debits' => $movement['debits'],
                'total_payable' => $payable,
                'closing_credit' => $closing,
                'items' => $items,
            ];
        }

        $warnings = [];
        if ($rate && ! Decimal::isZero($rate->unrecovered)) {
            $warnings[] = Decimal::compare($rate->unrecovered, '0') > 0
                ? "The fixed rate leaves {$rate->unrecovered} of food cost unrecovered."
                : 'The fixed rate recovers '.ltrim($rate->unrecovered, '-').' more than the food cost.';
        }
        if ($policy->strategy === 'equal' && ! Decimal::isZero($units['guest_total'])) {
            $warnings[] = 'Guest meals are not charged under the equal strategy.';
        }

        return [
            'engine' => self::CALCULATION_ENGINE,
            'period' => ['id' => $period->id, 'name' => $period->name, 'start' => $start, 'end' => $end],
            'policy' => $policy->toArray(),
            'strategy' => $strategy->key(),
            'strategy_breakdown' => $rate ? $strategy->getBreakdown() : null,
            'food_pool' => $food,
            'units' => [
                'members' => $units['member_total'],
                'sponsored_guests' => Decimal::sum($units['sponsored_guests']),
                'unsponsored_guests' => $units['unsponsored_guests'],
                'total' => $rate?->totalUnits ?? Decimal::add($units['member_total'], $units['guest_total']),
            ],
            'meal_rate' => $rate?->rate,
            'unrecovered_food' => $rate?->unrecovered ?? '0.00',
            'other_expenses' => ['total' => $other['total'], 'lines' => $other['lines'], 'excluded' => $other['excluded']],
            'members' => $rows,
            'totals' => [
                'deposits' => Decimal::sum(array_column($rows, 'total_deposits'), 2),
                'charges' => Decimal::sum(array_column($rows, 'total_payable'), 2),
                'adjustments' => bcsub(Decimal::sum(array_column($rows, 'other_credits'), 2), Decimal::sum(array_column($rows, 'other_debits'), 2), 2),
                'closing' => Decimal::sum(array_column($rows, 'closing_credit'), 2),
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Save (or replace) a reviewable draft. Not allowed once a final version exists.
     */
    public function generateDraft(AccountingPeriod $period, User $actor): MonthlySnapshot
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if (! in_array($period->status, [PeriodStatus::Open, PeriodStatus::Reviewing], true)) {
                throw ValidationException::withMessages(['settlement' => "The period is {$period->status->value}."]);
            }

            $this->assertNoFinal($period);
            $result = $this->calculate($period);

            if ($result['errors']) {
                throw ValidationException::withMessages(['settlement' => $result['errors']]);
            }

            $period->snapshots()->where('status', MonthlySnapshot::DRAFT)->get()->each->delete();
            $snapshot = $this->store($period, $result, MonthlySnapshot::DRAFT, Settlement::DRAFT, $actor);
            $this->audit->log('settlement.draft_generated', $snapshot, new: ['version' => $snapshot->calculation_version, 'rate' => $result['meal_rate']]);

            return $snapshot;
        });
    }

    /**
     * Finalize: recalculate, save an immutable snapshot, post member charges,
     * reconcile against the ledger and mark the period finalized — all or nothing.
     */
    public function finalize(AccountingPeriod $period, User $actor): MonthlySnapshot
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $mess = Mess::findOrFail($period->mess_id);

            if ($period->status !== PeriodStatus::Reviewing) {
                throw ValidationException::withMessages(['settlement' => 'Move the period to review before finalizing.']);
            }

            $this->assertNoFinal($period);

            if (($pending = $this->periods->pendingCount($period) + $this->pendingFinance($period)) > 0) {
                throw ValidationException::withMessages(['settlement' => "Resolve {$pending} pending item(s) (meals, bazar, expenses, deposits or adjustments) before finalizing."]);
            }

            $result = $this->calculate($period);
            if ($result['errors']) {
                throw ValidationException::withMessages(['settlement' => $result['errors']]);
            }

            $period->snapshots()->where('status', MonthlySnapshot::DRAFT)->get()->each->delete();
            $snapshot = $this->store($period, $result, MonthlySnapshot::FINAL, Settlement::FINALIZED, $actor);

            $memberCredit = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);
            $lines = [];
            foreach ($snapshot->settlements()->with('member')->get() as $s) {
                if (Decimal::compare($s->total_payable, '0') > 0) {
                    $lines[] = ['account' => $memberCredit, 'member' => $s->member, 'debit' => $s->total_payable, 'description' => "Settlement {$period->name}"];
                }
            }

            if ($lines) {
                $lines[] = ['account' => $this->setup->account($mess, FinancialAccount::EXPENSE_RECOVERY), 'credit' => Decimal::sum(array_column($lines, 'debit'), 2)];
                $tx = $this->ledger->post($mess, $period->end_date->toDateString(), "Settlement {$period->name} (v{$snapshot->calculation_version})", $lines, $actor, $snapshot);
                // Linked inside the same transaction that created the snapshot; the model's immutability guard is for later edits.
                DB::table('monthly_snapshots')->where('id', $snapshot->id)->update(['ledger_transaction_id' => $tx->id]);
            }

            if ($problems = $this->reconciliation->check($mess, $period, $result)) {
                throw ValidationException::withMessages(['settlement' => ['Reconciliation failed; nothing was finalized.', ...$problems]]);
            }

            $period->forceFill(['status' => PeriodStatus::Finalized, 'finalized_by' => $actor->id, 'finalized_at' => now()])->save();
            $this->audit->log('settlement.finalized', $snapshot, new: ['version' => $snapshot->calculation_version, 'rate' => $result['meal_rate'], 'charges' => $result['totals']['charges']]);

            return $snapshot->fresh();
        });
    }

    /**
     * Authorized reopen: the final snapshot is kept but superseded, its ledger
     * charges are reversed, and the period returns to open for corrections.
     */
    public function reopen(AccountingPeriod $period, User $actor, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $actor, $reason) {
            $period = $this->periods->reopen($period, $actor, $reason);

            foreach ($period->snapshots()->where('status', MonthlySnapshot::FINAL)->get() as $snapshot) {
                if ($snapshot->ledger_transaction_id) {
                    $this->ledger->reverse(LedgerTransaction::findOrFail($snapshot->ledger_transaction_id), $actor, "Period reopened: {$reason}");
                }

                $snapshot->forceFill(['status' => MonthlySnapshot::SUPERSEDED, 'superseded_at' => now(), 'superseded_reason' => $reason])->save();
                $snapshot->settlements()->update(['status' => Settlement::SUPERSEDED]);
                $this->audit->log('settlement.superseded', $snapshot, new: ['version' => $snapshot->calculation_version, 'reason' => $reason]);
            }

            return $period;
        });
    }

    private function store(AccountingPeriod $period, array $result, string $snapshotStatus, string $settlementStatus, User $actor): MonthlySnapshot
    {
        $snapshot = MonthlySnapshot::create([
            'tenant_id' => $period->tenant_id,
            'mess_id' => $period->mess_id,
            'period_id' => $period->id,
            'calculation_version' => (int) $period->snapshots()->max('calculation_version') + 1,
            'status' => $snapshotStatus,
            'strategy' => $result['strategy'],
            'total_food_expense' => $result['food_pool']['total'],
            'total_weighted_meals' => $result['units']['total'],
            'calculated_meal_rate' => $result['meal_rate'] ?? '0',
            'total_other_expenses' => $result['other_expenses']['total'],
            'total_deposits' => $result['totals']['deposits'],
            'total_adjustments' => $result['totals']['adjustments'],
            'calculation_details' => $result,
            'generated_by' => $actor->id,
            'generated_at' => now(),
            'finalized_at' => $snapshotStatus === MonthlySnapshot::FINAL ? now() : null,
        ]);

        foreach ($result['members'] as $memberId => $row) {
            $settlement = $snapshot->settlements()->create([
                'tenant_id' => $period->tenant_id,
                'mess_id' => $period->mess_id,
                'period_id' => $period->id,
                'member_id' => $memberId,
                'status' => $this->initialStatus($settlementStatus, $row['closing_credit']),
                'finalized_at' => $settlementStatus === Settlement::FINALIZED ? now() : null,
            ] + collect($row)->only(['opening_credit', 'meal_units', 'meal_cost', 'guest_cost', 'other_expense', 'other_credits', 'other_debits', 'total_deposits', 'total_payable', 'closing_credit'])->all());

            $settlement->items()->createMany(array_map(fn ($i) => [
                'item_type' => $i['type'],
                'description' => $i['description'],
                'quantity' => $i['quantity'],
                'unit_rate' => $i['unit_rate'],
                'amount' => $i['amount'],
                'calculation_reference' => $i['ref'],
            ], $row['items']));
        }

        return $snapshot;
    }

    private function initialStatus(string $status, string $closing): string
    {
        // A zero balance needs no payment, so it is settled immediately.
        return $status === Settlement::FINALIZED && Decimal::isZero($closing) ? Settlement::PAID : $status;
    }

    private function assertNoFinal(AccountingPeriod $period): void
    {
        if ($period->snapshots()->where('status', MonthlySnapshot::FINAL)->exists()) {
            throw ValidationException::withMessages(['settlement' => 'This period already has a final settlement. Reopen the period to recalculate.']);
        }
    }

    private function pendingFinance(AccountingPeriod $period): int
    {
        return $period->bazarEntries()->where('status', 'submitted')->count()
            + $period->expenses()->where('status', 'submitted')->count()
            + $period->deposits()->where('status', DepositStatus::Pending)->count()
            + $period->financialAdjustments()->where('status', 'submitted')->count();
    }
}
