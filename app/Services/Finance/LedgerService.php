<?php

namespace App\Services\Finance;

use App\Models\FinancialAccount;
use App\Models\LedgerTransaction;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Double-entry posting. Every posted transaction balances (Σdebit = Σcredit),
 * is immutable, and is corrected only by a reversal that references it.
 */
class LedgerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{account: FinancialAccount, debit?: string, credit?: string, member?: ?MessMember, description?: ?string}>  $lines
     */
    public function post(Mess $mess, string $date, string $description, array $lines, User $actor, ?Model $reference = null): LedgerTransaction
    {
        return $this->write($mess, $date, $description, $lines, $actor, $reference?->getMorphClass(), $reference?->getKey(), null);
    }

    private function write(Mess $mess, string $date, string $description, array $lines, User $actor, ?string $referenceType, ?int $referenceId, ?LedgerTransaction $reverses): LedgerTransaction
    {
        $debits = '0.00';
        $credits = '0.00';
        $rows = [];

        foreach ($lines as $line) {
            $debit = Decimal::of($line['debit'] ?? '0', 2);
            $credit = Decimal::of($line['credit'] ?? '0', 2);

            if (Decimal::isZero($debit) && Decimal::isZero($credit)) {
                continue; // zero lines carry no information
            }

            if (Decimal::compare($debit, '0') < 0 || Decimal::compare($credit, '0') < 0 || (! Decimal::isZero($debit) && ! Decimal::isZero($credit))) {
                throw new InvalidArgumentException('Each ledger line must have either a positive debit or a positive credit.');
            }

            if ($line['account']->mess_id !== $mess->id || (($line['member'] ?? null) && $line['member']->mess_id !== $mess->id)) {
                throw new InvalidArgumentException('Ledger lines must belong to the posting mess.');
            }

            $debits = bcadd($debits, $debit, 2);
            $credits = bcadd($credits, $credit, 2);
            $rows[] = [
                'mess_id' => $mess->id,
                'account_id' => $line['account']->id,
                'member_id' => ($line['member'] ?? null)?->id,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        if (count($rows) < 2 || Decimal::compare($debits, $credits, 2) !== 0) {
            throw new InvalidArgumentException("Unbalanced transaction: debits {$debits}, credits {$credits}.");
        }

        return DB::transaction(function () use ($mess, $date, $description, $rows, $actor, $referenceType, $referenceId, $reverses) {
            $tx = $mess->ledgerTransactions()->create([
                'tenant_id' => $mess->tenant_id,
                'transaction_number' => $this->nextNumber($mess),
                'transaction_date' => $date,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'posted',
                'reverses_transaction_id' => $reverses?->id,
                'created_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $tx->lines()->createMany($rows);
            $this->audit->log($reverses ? 'ledger.reversed' : 'ledger.posted', $tx, new: ['number' => $tx->transaction_number, 'amount' => collect($rows)->sum('debit')]);

            return $tx;
        });
    }

    /**
     * Post the mirror image of $original and mark the original reversed. The
     * reversal is dated like the original by default so period totals net to zero.
     */
    public function reverse(LedgerTransaction $original, User $actor, string $reason, ?string $date = null): LedgerTransaction
    {
        return DB::transaction(function () use ($original, $actor, $reason, $date) {
            $original = LedgerTransaction::query()->lockForUpdate()->with('lines.account', 'lines.member')->findOrFail($original->id);

            if ($original->status !== 'posted' || $original->reverses_transaction_id) {
                throw new LogicException("Transaction {$original->transaction_number} cannot be reversed.");
            }

            $mess = Mess::findOrFail($original->mess_id);
            $lines = $original->lines->map(fn ($l) => [
                'account' => $l->account,
                'member' => $l->member,
                'debit' => $l->credit,
                'credit' => $l->debit,
                'description' => $l->description,
            ])->all();

            $reversal = $this->write(
                $mess,
                $date ?? $original->transaction_date->toDateString(),
                "Reversal of {$original->transaction_number}: {$reason}",
                $lines,
                $actor,
                $original->reference_type,
                $original->reference_id,
                $original,
            );

            $original->status = 'reversed';
            $original->save();

            return $reversal;
        });
    }

    /**
     * Net balance in the account's normal direction (opening + movements).
     */
    public function accountBalance(FinancialAccount $account): string
    {
        $row = $account->lines()->selectRaw('COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')->first();
        $movement = $account->account_type->isDebitNormal() ? bcsub($row->d, $row->c, 2) : bcsub($row->c, $row->d, 2);

        return bcadd(Decimal::of($account->opening_balance, 2), $movement, 2);
    }

    /**
     * What the mess owes the member on the member-credit account (positive = mess owes member).
     */
    public function memberCredit(MessMember $member, FinancialAccount $memberCreditAccount): string
    {
        $row = $memberCreditAccount->lines()->where('member_id', $member->id)
            ->selectRaw('COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')->first();

        return bcsub($row->c, $row->d, 2);
    }

    /**
     * Member credit including only transactions dated on or before $date.
     */
    public function memberCreditAsOf(MessMember $member, FinancialAccount $memberCreditAccount, string $date): string
    {
        $row = DB::table('ledger_lines')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_lines.transaction_id')
            ->where('ledger_lines.account_id', $memberCreditAccount->id)
            ->where('ledger_lines.member_id', $member->id)
            ->whereDate('ledger_transactions.transaction_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(ledger_lines.debit), 0) AS d, COALESCE(SUM(ledger_lines.credit), 0) AS c')
            ->first();

        return bcsub($row->c, $row->d, 2);
    }

    /**
     * Net movement (debit − credit) on an account within a date range.
     */
    public function movement(FinancialAccount $account, string $from, string $to): string
    {
        $row = DB::table('ledger_lines')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_lines.transaction_id')
            ->where('ledger_lines.account_id', $account->id)
            ->whereBetween('ledger_transactions.transaction_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(ledger_lines.debit), 0) AS d, COALESCE(SUM(ledger_lines.credit), 0) AS c')
            ->first();

        return bcsub($row->d, $row->c, 2);
    }

    /**
     * @return array{debit: string, credit: string}
     */
    public function totals(Mess $mess): array
    {
        $row = DB::table('ledger_lines')->where('mess_id', $mess->id)
            ->selectRaw('COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')->first();

        return ['debit' => Decimal::of($row->d, 2), 'credit' => Decimal::of($row->c, 2)];
    }

    private function nextNumber(Mess $mess): string
    {
        // Lock the mess row so concurrent postings get distinct sequential numbers.
        Mess::query()->whereKey($mess->id)->lockForUpdate()->first();
        $count = LedgerTransaction::query()->where('mess_id', $mess->id)->count();

        return 'TX-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
