<?php

namespace App\Services\Finance;

use App\Enums\DepositStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Models\Deposit;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Member deposits. Approval posts Dr cash/bank/wallet, Cr member balance; a
 * reversal posts the mirror image. Posted deposits are never edited or deleted.
 */
class DepositService
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly FinanceSetupService $setup,
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{member_id: int, amount: string, payment_method: string, paid_at: string, transaction_reference?: ?string, notes?: ?string}  $data
     */
    public function record(Mess $mess, array $data, User $actor, ?string $receiptPath = null): Deposit
    {
        $method = PaymentMethod::from($data['payment_method']);

        if (! in_array($method, PaymentMethod::forDeposits(), true)) {
            throw ValidationException::withMessages(['payment_method' => 'Deposits are received in cash, bank or mobile wallet.']);
        }

        $member = $mess->members()->find($data['member_id'])
            ?? throw ValidationException::withMessages(['member_id' => 'Member must belong to this mess.']);

        if ($member->membership_status === MembershipStatus::Invited) {
            throw ValidationException::withMessages(['member_id' => 'This member has not joined yet.']);
        }

        $amount = Decimal::of($data['amount'], 2);
        if (Decimal::compare($amount, '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $paidAt = Carbon::parse($data['paid_at'], $mess->timezone);
        $period = $this->periods->openPeriodFor($mess, $paidAt->toDateString(), 'paid_at');
        $reference = trim((string) ($data['transaction_reference'] ?? '')) ?: null;

        try {
            $deposit = Deposit::create([
                'tenant_id' => $mess->tenant_id,
                'mess_id' => $mess->id,
                'period_id' => $period->id,
                'member_id' => $member->id,
                'amount' => $amount,
                'payment_method' => $method,
                'financial_account_id' => $this->setup->account($mess, $method->value)->id,
                'transaction_reference' => $reference,
                'receipt_path' => $receiptPath,
                'paid_at' => $paidAt,
                'status' => DepositStatus::Pending,
                'received_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['transaction_reference' => "A {$method->label()} deposit with reference {$reference} is already recorded."]);
        }

        $this->audit->log('deposit.recorded', $deposit, new: ['member_id' => $member->id, 'amount' => $amount, 'method' => $method->value]);

        return $deposit;
    }

    public function approve(Deposit $deposit, User $actor): Deposit
    {
        return DB::transaction(function () use ($deposit, $actor) {
            $deposit = Deposit::query()->lockForUpdate()->with('member', 'account', 'period')->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Pending) {
                throw ValidationException::withMessages(['status' => "This deposit is {$deposit->status->value}."]);
            }

            if (! $deposit->period->status->acceptsApprovals()) {
                throw ValidationException::withMessages(['status' => "The period \"{$deposit->period->name}\" is {$deposit->period->status->value}."]);
            }

            $mess = Mess::findOrFail($deposit->mess_id);
            $tx = $this->ledger->post($mess, $deposit->paid_at->toDateString(), "Deposit from {$deposit->member->full_name}", [
                ['account' => $deposit->account, 'debit' => $deposit->amount],
                ['account' => $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT), 'member' => $deposit->member, 'credit' => $deposit->amount],
            ], $actor, $deposit);

            $deposit->forceFill(['status' => DepositStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'ledger_transaction_id' => $tx->id])->save();
            $this->audit->log('deposit.approved', $deposit, new: ['ledger' => $tx->transaction_number]);

            return $deposit;
        });
    }

    public function reject(Deposit $deposit, User $actor, string $reason): Deposit
    {
        return DB::transaction(function () use ($deposit, $reason) {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Pending) {
                throw ValidationException::withMessages(['status' => "This deposit is {$deposit->status->value}."]);
            }

            $deposit->forceFill(['status' => DepositStatus::Rejected, 'rejection_reason' => $reason])->save();
            $this->audit->log('deposit.rejected', $deposit, new: ['reason' => $reason]);

            return $deposit;
        });
    }

    public function reverse(Deposit $deposit, User $actor, string $reason): Deposit
    {
        return DB::transaction(function () use ($deposit, $actor, $reason) {
            $deposit = Deposit::query()->lockForUpdate()->with('ledgerTransaction', 'period')->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Approved) {
                throw ValidationException::withMessages(['status' => 'Only approved deposits can be reversed.']);
            }

            if (! $deposit->period->status->acceptsEntries()) {
                throw ValidationException::withMessages(['status' => "The period \"{$deposit->period->name}\" is not open."]);
            }

            $this->ledger->reverse($deposit->ledgerTransaction, $actor, $reason);
            $deposit->forceFill(['status' => DepositStatus::Reversed, 'rejection_reason' => $reason])->save();
            $this->audit->log('deposit.reversed', $deposit, new: ['reason' => $reason]);

            return $deposit;
        });
    }
}
