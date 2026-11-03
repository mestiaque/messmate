<?php

namespace App\Services\Settlement;

use App\Enums\EntryStatus;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\FinancialAdjustment;
use App\Models\LedgerTransaction;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual credits (mess owes the member more) and debits (member owes more),
 * e.g. a fine or compensation. Posted on approval, reversed on void.
 */
class FinancialAdjustmentService
{
    public function __construct(
        private readonly FinanceSetupService $setup,
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function create(AccountingPeriod $period, MessMember $member, string $type, string $amount, string $reason, User $actor, bool $approve = false): FinancialAdjustment
    {
        if (! $period->status->acceptsEntries()) {
            throw ValidationException::withMessages(['period_id' => "The period \"{$period->name}\" is not open."]);
        }

        if ($member->mess_id !== $period->mess_id) {
            throw ValidationException::withMessages(['member_id' => 'Member does not belong to this mess.']);
        }

        if (! in_array($type, ['credit', 'debit'], true) || Decimal::compare(Decimal::of($amount, 2), '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'Choose credit or debit and a positive amount.']);
        }

        $today = now(Mess::findOrFail($period->mess_id)->timezone)->toDateString();
        $adjustment = FinancialAdjustment::create([
            'tenant_id' => $period->tenant_id,
            'mess_id' => $period->mess_id,
            'period_id' => $period->id,
            'member_id' => $member->id,
            'adjustment_type' => $type,
            'amount' => Decimal::of($amount, 2),
            'reason' => $reason,
            'status' => EntryStatus::Submitted,
            // Posted inside the period so it lands in that period's settlement.
            'effective_date' => min(max($today, $period->start_date->toDateString()), $period->end_date->toDateString()),
            'created_by' => $actor->id,
        ]);
        $this->audit->log('financial_adjustment.created', $adjustment, new: $adjustment->only(['member_id', 'adjustment_type', 'amount', 'reason']));

        return $approve ? $this->approve($adjustment, $actor) : $adjustment;
    }

    public function approve(FinancialAdjustment $adjustment, User $actor): FinancialAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor) {
            $adjustment = FinancialAdjustment::query()->lockForUpdate()->with('member', 'period')->findOrFail($adjustment->id);

            if ($adjustment->status !== EntryStatus::Submitted || ! $adjustment->period->status->acceptsApprovals()) {
                throw ValidationException::withMessages(['status' => 'This adjustment cannot be approved now.']);
            }

            $mess = Mess::findOrFail($adjustment->mess_id);
            $member = ['account' => $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT), 'member' => $adjustment->member];
            $lines = $adjustment->adjustment_type === 'credit'
                ? [['account' => $this->setup->account($mess, FinancialAccount::OTHER_EXPENSE), 'debit' => $adjustment->amount], $member + ['credit' => $adjustment->amount]]
                : [$member + ['debit' => $adjustment->amount], ['account' => $this->setup->account($mess, FinancialAccount::EXPENSE_RECOVERY), 'credit' => $adjustment->amount]];

            $tx = $this->ledger->post($mess, $adjustment->effective_date->toDateString(), "Adjustment ({$adjustment->adjustment_type}): {$adjustment->reason}", $lines, $actor, $adjustment);
            $adjustment->forceFill(['status' => EntryStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'ledger_transaction_id' => $tx->id])->save();
            $this->audit->log('financial_adjustment.approved', $adjustment, new: ['ledger' => $tx->transaction_number]);

            return $adjustment;
        });
    }

    public function reject(FinancialAdjustment $adjustment, User $actor, string $reason): FinancialAdjustment
    {
        if ($adjustment->status !== EntryStatus::Submitted) {
            throw ValidationException::withMessages(['status' => 'Only submitted adjustments can be rejected.']);
        }

        $adjustment->update(['status' => EntryStatus::Rejected]);
        $this->audit->log('financial_adjustment.rejected', $adjustment, new: ['reason' => $reason]);

        return $adjustment;
    }

    public function void(FinancialAdjustment $adjustment, User $actor, string $reason): FinancialAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $reason) {
            $adjustment = FinancialAdjustment::query()->lockForUpdate()->with('period')->findOrFail($adjustment->id);

            if ($adjustment->status === EntryStatus::Voided || ! $adjustment->period->status->acceptsEntries()) {
                throw ValidationException::withMessages(['status' => 'This adjustment cannot be voided now.']);
            }

            if ($adjustment->ledger_transaction_id) {
                $this->ledger->reverse(LedgerTransaction::findOrFail($adjustment->ledger_transaction_id), $actor, $reason);
            }

            $adjustment->forceFill(['status' => EntryStatus::Voided])->save();
            $this->audit->log('financial_adjustment.voided', $adjustment, new: ['reason' => $reason]);

            return $adjustment;
        });
    }
}
