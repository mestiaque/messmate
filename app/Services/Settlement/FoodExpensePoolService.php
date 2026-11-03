<?php

namespace App\Services\Settlement;

use App\Enums\EntryStatus;
use App\Enums\ExpenseCategoryType;
use App\Models\AccountingPeriod;
use App\Support\Decimal;

/**
 * Food pool = approved bazar food shares + approved food-category expenses in
 * the period. Bazar purchases are never also expenses, so nothing is counted twice.
 */
class FoodExpensePoolService
{
    public function calculate(AccountingPeriod $period): array
    {
        $bazar = $period->bazarEntries()->where('status', EntryStatus::Approved)->get(['id', 'total_amount', 'food_amount']);
        $foodExpenses = $period->expenses()->where('expenses.status', EntryStatus::Approved)
            ->whereHas('category', fn ($q) => $q->where('category_type', ExpenseCategoryType::Food))
            ->get(['expenses.id', 'amount']);

        $bazarFood = Decimal::sum($bazar->pluck('food_amount'), 2);
        $expenseFood = Decimal::sum($foodExpenses->pluck('amount'), 2);

        return [
            'bazar_food' => $bazarFood,
            'bazar_ids' => $bazar->pluck('id')->all(),
            'food_expenses' => $expenseFood,
            'food_expense_ids' => $foodExpenses->pluck('id')->all(),
            'total' => bcadd($bazarFood, $expenseFood, 2),
            // Non-food part of bazar purchases is shared like an equal-split expense.
            'bazar_non_food' => bcsub(Decimal::sum($bazar->pluck('total_amount'), 2), $bazarFood, 2),
        ];
    }
}
