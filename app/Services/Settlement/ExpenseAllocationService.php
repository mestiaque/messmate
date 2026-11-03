<?php

namespace App\Services\Settlement;

use App\Enums\AllocationMethod;
use App\Enums\EntryStatus;
use App\Enums\ExpenseCategoryType;
use App\Models\AccountingPeriod;
use App\Support\Decimal;

/**
 * Shares approved non-food expenses (and the non-food part of bazar purchases)
 * between members according to each expense's allocation method.
 */
class ExpenseAllocationService
{
    /**
     * @param  array<int, array{days: int}>  $eligible  member_id => eligibility info
     * @param  array<int, string>  $memberUnits  member_id => meal units
     * @return array{shares: array<int, list<array>>, total: string, excluded: list<array>, errors: list<string>, lines: list<array>}
     */
    public function allocate(AccountingPeriod $period, array $eligible, array $memberUnits, SettlementPolicy $policy, string $bazarNonFood): array
    {
        $shares = [];
        $errors = [];
        $excluded = [];
        $lines = [];
        $total = '0.00';

        $equalWeights = array_map(fn ($e) => $policy->equalSplitBasis === 'days' ? (string) $e['days'] : '1', $eligible);
        $mealWeights = array_filter($memberUnits, fn ($u) => Decimal::compare($u, '0') > 0);

        $expenses = $period->expenses()->where('expenses.status', EntryStatus::Approved)
            ->whereHas('category', fn ($q) => $q->where('category_type', '!=', ExpenseCategoryType::Food))
            ->with('allocations')->orderBy('expense_date')->orderBy('id')->get();

        $items = $expenses->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'amount' => Decimal::of($e->amount, 2), 'method' => $e->allocation_method, 'saved' => $e->allocations])->all();

        if (Decimal::compare($bazarNonFood, '0') > 0) {
            $items[] = ['id' => null, 'title' => 'Bazar non-food items', 'amount' => $bazarNonFood, 'method' => AllocationMethod::Equal, 'saved' => collect()];
        }

        foreach ($items as $item) {
            $split = match ($item['method']) {
                AllocationMethod::Excluded => null,
                AllocationMethod::Equal => $equalWeights ? Decimal::allocate($item['amount'], $equalWeights) : false,
                AllocationMethod::MealBased => $mealWeights ? Decimal::allocate($item['amount'], $mealWeights) : false,
                AllocationMethod::Custom, AllocationMethod::UsageBased => $item['saved']->mapWithKeys(fn ($a) => [$a->member_id => Decimal::of($a->allocated_amount, 2)])->all(),
            };

            if ($split === null) {
                $excluded[] = ['expense_id' => $item['id'], 'title' => $item['title'], 'amount' => $item['amount']];

                continue;
            }

            if ($split === false) {
                $errors[] = "\"{$item['title']}\" cannot be shared: no ".($item['method'] === AllocationMethod::MealBased ? 'meals' : 'eligible members').' in this period.';

                continue;
            }

            if (Decimal::compare(Decimal::sum($split, 2), $item['amount'], 2) !== 0) {
                $errors[] = "\"{$item['title']}\" member shares do not add up to its amount.";

                continue;
            }

            foreach ($split as $memberId => $amount) {
                if (! Decimal::isZero($amount)) {
                    $shares[$memberId][] = ['expense_id' => $item['id'], 'title' => $item['title'], 'method' => $item['method']->value, 'amount' => $amount];
                }
            }

            $lines[] = ['expense_id' => $item['id'], 'title' => $item['title'], 'method' => $item['method']->value, 'amount' => $item['amount']];
            $total = bcadd($total, $item['amount'], 2);
        }

        return ['shares' => $shares, 'total' => $total, 'excluded' => $excluded, 'errors' => $errors, 'lines' => $lines];
    }
}
