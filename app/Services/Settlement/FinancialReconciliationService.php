<?php

namespace App\Services\Settlement;

use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\MessMember;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Support\Decimal;

/**
 * Cross-checks a calculation against the ledger. Run after the settlement
 * charges are posted; any mismatch aborts finalization.
 */
class FinancialReconciliationService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinanceSetupService $setup,
    ) {}

    /**
     * @return list<string> problems; empty when everything reconciles
     */
    public function check(Mess $mess, AccountingPeriod $period, array $result): array
    {
        $problems = [];
        $end = $period->end_date->toDateString();
        $memberCredit = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);

        foreach ($result['members'] as $id => $row) {
            $ledgerBalance = $this->ledger->memberCreditAsOf(MessMember::findOrFail($id), $memberCredit, $end);

            if (Decimal::compare($ledgerBalance, $row['closing_credit'], 2) !== 0) {
                $problems[] = "{$row['name']}: settlement closing {$row['closing_credit']} but ledger shows {$ledgerBalance}.";
            }
        }

        $foodMovement = $this->ledger->movement($this->setup->account($mess, FinancialAccount::FOOD_EXPENSE), $period->start_date->toDateString(), $end);
        if (Decimal::compare($foodMovement, $result['food_pool']['total'], 2) !== 0) {
            $problems[] = "Food pool {$result['food_pool']['total']} does not match the food expense account movement {$foodMovement}.";
        }

        $totals = $this->ledger->totals($mess);
        if (Decimal::compare($totals['debit'], $totals['credit'], 2) !== 0) {
            $problems[] = "The ledger is out of balance (Dr {$totals['debit']} / Cr {$totals['credit']}).";
        }

        return $problems;
    }
}
