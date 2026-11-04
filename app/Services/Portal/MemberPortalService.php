<?php

namespace App\Services\Portal;

use App\Enums\EntryStatus;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\MessMember;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Services\Settlement\SettlementService;
use App\Support\Decimal;

/**
 * Everything a member sees about themself. Estimates come from the live
 * settlement preview and are labelled as estimates in the UI.
 */
class MemberPortalService
{
    public function __construct(
        private readonly SettlementService $settlements,
        private readonly LedgerService $ledger,
        private readonly FinanceSetupService $setup,
    ) {}

    public function summary(Mess $mess, MessMember $member, ?AccountingPeriod $period): array
    {
        $memberCredit = $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT);
        $today = now($mess->timezone)->toDateString();
        $preview = $period ? $this->settlements->calculate($period) : null;
        $row = $preview['members'][$member->id] ?? null;

        return [
            'today' => $member->mealEntries()->whereDate('meal_date', $today)->where('status', '!=', EntryStatus::Voided)->with('items.mealType:id,name')->first(),
            'units' => $period ? Decimal::of($period->mealEntries()->where('member_id', $member->id)->whereIn('status', [EntryStatus::Submitted, EntryStatus::Approved])->sum('total_units')) : '0',
            'rate' => $preview['meal_rate'] ?? null,
            'estimate' => $row,
            'balance' => $this->ledger->memberCredit($member, $memberCredit),
            'transactions' => $memberCredit->lines()->where('member_id', $member->id)->with('transaction:id,transaction_number,transaction_date,description,status')
                ->latest('id')->limit(15)->get(),
            'statements' => $member->settlements()->whereIn('status', ['finalized', 'partially_paid', 'paid'])->with('period:id,name')->latest('id')->get(),
            'announcements' => $mess->announcements()->visible()->limit(5)->get(),
        ];
    }
}
