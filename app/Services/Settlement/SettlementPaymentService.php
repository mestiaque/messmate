<?php

namespace App\Services\Settlement;

use App\Enums\PaymentMethod;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\Settlement;
use App\Models\SettlementPayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collecting a negative balance from a member, or refunding a positive one.
 * The direction is decided by the settlement, never by the form.
 */
class SettlementPaymentService
{
    public function __construct(
        private readonly FinanceSetupService $setup,
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function record(Settlement $settlement, string $amount, PaymentMethod $method, ?string $reference, User $actor): SettlementPayment
    {
        if (! in_array($method, PaymentMethod::forDeposits(), true)) {
            throw ValidationException::withMessages(['payment_method' => 'Use cash, bank or mobile wallet.']);
        }

        return DB::transaction(function () use ($settlement, $amount, $method, $reference, $actor) {
            $settlement = Settlement::query()->lockForUpdate()->with('member', 'period')->findOrFail($settlement->id);
            $amount = Decimal::of($amount, 2);

            if (! $settlement->isPayable()) {
                throw ValidationException::withMessages(['amount' => 'Nothing is outstanding on this settlement.']);
            }

            if (Decimal::compare($amount, '0') <= 0 || Decimal::compare($amount, $settlement->outstanding(), 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be between 0.01 and the outstanding '.$settlement->outstanding().'.']);
            }

            $mess = Mess::findOrFail($settlement->mess_id);
            $isRefund = Decimal::compare($settlement->closing_credit, '0') > 0;
            $money = $this->setup->account($mess, $method->value);
            $memberLine = ['account' => $this->setup->account($mess, FinancialAccount::MEMBER_CREDIT), 'member' => $settlement->member];
            $lines = $isRefund
                ? [$memberLine + ['debit' => $amount], ['account' => $money, 'credit' => $amount]]
                : [['account' => $money, 'debit' => $amount], $memberLine + ['credit' => $amount]];

            $paidAt = now($mess->timezone);
            $tx = $this->ledger->post($mess, $paidAt->toDateString(), ($isRefund ? 'Refund to ' : 'Settlement payment from ').$settlement->member->full_name." ({$settlement->period->name})", $lines, $actor, $settlement);

            $payment = $settlement->payments()->create([
                'mess_id' => $settlement->mess_id,
                'amount' => $amount,
                'payment_type' => $isRefund ? 'refund' : 'collection',
                'payment_method' => $method,
                'transaction_reference' => $reference,
                'ledger_transaction_id' => $tx->id,
                'paid_at' => $paidAt,
                'received_by' => $actor->id,
            ]);

            $paid = bcadd($settlement->paid_amount, $amount, 2);
            $settlement->forceFill([
                'paid_amount' => $paid,
                'status' => Decimal::compare($paid, ltrim((string) $settlement->closing_credit, '-'), 2) >= 0 ? Settlement::PAID : Settlement::PARTIALLY_PAID,
            ])->save();

            $this->audit->log($isRefund ? 'settlement.refunded' : 'settlement.collected', $settlement, new: ['amount' => $amount, 'ledger' => $tx->transaction_number]);

            return $payment;
        });
    }
}
