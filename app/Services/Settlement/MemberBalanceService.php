<?php

namespace App\Services\Settlement;

use App\Enums\DepositStatus;
use App\Enums\EntryStatus;
use App\Enums\PaymentMethod;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\MessMember;
use App\Models\SettlementPayment;
use App\Services\Finance\LedgerService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

/**
 * Money movements on each member's balance during a period, read from the source
 * documents. Opening credit is the ledger balance on the day before the period
 * starts, which carries every earlier settlement forward automatically.
 */
class MemberBalanceService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return array{opening: string, deposits: string, credits: string, debits: string, items: list<array>}
     */
    public function movements(AccountingPeriod $period, MessMember $member, FinancialAccount $memberCredit): array
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $items = [];

        $opening = $this->ledger->memberCreditAsOf($member, $memberCredit, Carbon::parse($start)->subDay()->toDateString());

        $deposits = $period->deposits()->where('member_id', $member->id)->where('status', DepositStatus::Approved)->orderBy('paid_at')->get();
        foreach ($deposits as $d) {
            $items[] = ['type' => 'deposit', 'description' => 'Deposit '.$d->paid_at->format('d M').($d->transaction_reference ? " ({$d->transaction_reference})" : ''), 'amount' => Decimal::of($d->amount, 2), 'ref' => ['deposit_id' => $d->id]];
        }

        $credits = [];
        $debits = [];

        foreach ($period->bazarEntries()->where('status', EntryStatus::Approved)->where('payment_method', PaymentMethod::MemberPaid)->where('purchased_by', $member->id)->get() as $b) {
            $credits[] = ['type' => 'paid_purchase', 'description' => 'Paid for bazar '.$b->purchase_date->format('d M'), 'amount' => Decimal::of($b->total_amount, 2), 'ref' => ['bazar_entry_id' => $b->id]];
        }

        foreach ($period->expenses()->where('status', EntryStatus::Approved)->where('payment_method', PaymentMethod::MemberPaid)->where('paid_by_member_id', $member->id)->get() as $e) {
            $credits[] = ['type' => 'paid_expense', 'description' => "Paid for {$e->title}", 'amount' => Decimal::of($e->amount, 2), 'ref' => ['expense_id' => $e->id]];
        }

        foreach ($period->financialAdjustments()->where('member_id', $member->id)->where('status', EntryStatus::Approved)->get() as $a) {
            $row = ['type' => 'adjustment', 'description' => "Adjustment: {$a->reason}", 'amount' => Decimal::of($a->amount, 2), 'ref' => ['financial_adjustment_id' => $a->id]];
            $a->adjustment_type === 'credit' ? $credits[] = $row : $debits[] = $row;
        }

        $payments = SettlementPayment::query()
            ->whereHas('settlement', fn ($q) => $q->where('member_id', $member->id))
            ->whereBetween('paid_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->get();
        foreach ($payments as $p) {
            $row = ['type' => 'settlement_payment', 'description' => ($p->payment_type === 'refund' ? 'Refund paid ' : 'Settlement payment ').$p->paid_at->format('d M'), 'amount' => Decimal::of($p->amount, 2), 'ref' => ['settlement_payment_id' => $p->id]];
            $p->payment_type === 'refund' ? $debits[] = $row : $credits[] = $row;
        }

        foreach ($credits as $c) {
            $items[] = $c;
        }
        foreach ($debits as $d) {
            $items[] = ['amount' => '-'.$d['amount']] + $d;
        }

        return [
            'opening' => $opening,
            'deposits' => Decimal::sum($deposits->pluck('amount'), 2),
            'credits' => Decimal::sum(array_column($credits, 'amount'), 2),
            'debits' => Decimal::sum(array_column($debits, 'amount'), 2),
            'items' => $items,
        ];
    }
}
