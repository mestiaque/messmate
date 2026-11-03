<?php

namespace Tests\Feature\Finance;

use App\Models\FinancialAccount;
use App\Models\LedgerLine;
use App\Models\LedgerTransaction;
use App\Models\Role;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function accounts($mess): array
    {
        $setup = app(FinanceSetupService::class);

        return [$setup->account($mess, FinancialAccount::CASH), $setup->account($mess, FinancialAccount::FOOD_EXPENSE)];
    }

    public function test_balanced_transaction_posts_with_sequential_numbers(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$cash, $food] = $this->accounts($mess);
        $ledger = app(LedgerService::class);
        $owner = $mess->tenant->owner;

        $a = $ledger->post($mess, '2026-11-01', 'Rice', [['account' => $food, 'debit' => '100.50'], ['account' => $cash, 'credit' => '100.50']], $owner);
        $b = $ledger->post($mess, '2026-11-02', 'Fish', [['account' => $food, 'debit' => '50'], ['account' => $cash, 'credit' => '50']], $owner);

        $this->assertSame(['TX-000001', 'TX-000002'], [$a->transaction_number, $b->transaction_number]);
        $this->assertSame('-150.50', $ledger->accountBalance($cash));
        $this->assertSame('150.50', $ledger->accountBalance($food));
    }

    public function test_unbalanced_or_malformed_transactions_are_rejected(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$cash, $food] = $this->accounts($mess);
        $ledger = app(LedgerService::class);
        $owner = $mess->tenant->owner;

        foreach ([
            [['account' => $food, 'debit' => '100'], ['account' => $cash, 'credit' => '99.99']],
            [['account' => $food, 'debit' => '100']],
            [['account' => $food, 'debit' => '100', 'credit' => '100'], ['account' => $cash, 'credit' => '0']],
            [['account' => $food, 'debit' => '-5'], ['account' => $cash, 'credit' => '-5']],
        ] as $lines) {
            try {
                $ledger->post($mess, '2026-11-01', 'Bad', $lines, $owner);
                $this->fail('Malformed transaction accepted.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(0, LedgerTransaction::count());
    }

    public function test_accounts_from_another_mess_are_rejected(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$other] = $this->messWithOpenPeriod();
        [$cash] = $this->accounts($mess);
        [, $foreignFood] = $this->accounts($other);

        $this->expectException(InvalidArgumentException::class);
        app(LedgerService::class)->post($mess, '2026-11-01', 'X', [['account' => $foreignFood, 'debit' => '10'], ['account' => $cash, 'credit' => '10']], $mess->tenant->owner);
    }

    public function test_posted_transactions_and_lines_are_immutable(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$cash, $food] = $this->accounts($mess);
        $tx = app(LedgerService::class)->post($mess, '2026-11-01', 'Rice', [['account' => $food, 'debit' => '10'], ['account' => $cash, 'credit' => '10']], $mess->tenant->owner);

        foreach ([fn () => $tx->update(['description' => 'Edited']), fn () => $tx->delete(), fn () => $tx->lines()->first()->update(['debit' => 5]), fn () => $tx->lines()->first()->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Posted ledger data was modified.');
            } catch (LogicException) {
            }
        }

        // The database itself refuses a line with both or neither side set.
        $this->expectException(QueryException::class);
        DB::table('ledger_lines')->insert(['transaction_id' => $tx->id, 'mess_id' => $mess->id, 'account_id' => $cash->id, 'debit' => 5, 'credit' => 5]);
    }

    public function test_reversal_mirrors_the_original_and_can_happen_once(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$cash, $food] = $this->accounts($mess);
        $ledger = app(LedgerService::class);
        $owner = $mess->tenant->owner;
        $tx = $ledger->post($mess, '2026-11-01', 'Rice', [['account' => $food, 'debit' => '80'], ['account' => $cash, 'credit' => '80']], $owner);

        $reversal = $ledger->reverse($tx, $owner, 'Wrong amount');

        $this->assertSame('reversed', $tx->fresh()->status);
        $this->assertSame($tx->id, $reversal->reverses_transaction_id);
        $this->assertSame('0.00', $ledger->accountBalance($cash));
        $this->assertSame('0.00', $ledger->accountBalance($food));
        $this->assertSame('80.00', $reversal->lines()->where('account_id', $cash->id)->value('debit'));

        $this->expectException(LogicException::class);
        $ledger->reverse($tx->fresh(), $owner, 'Again');
    }

    public function test_ledger_screens_require_finance_permission(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $accountant = $this->memberOf($mess, Role::ACCOUNTANT);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        foreach (['ledger', 'ledger/balances', 'finance/report'] as $path) {
            $this->actingAs($member->user)->get("{$base}/{$path}")->assertForbidden();
            $this->actingAs($accountant->user)->get("{$base}/{$path}")->assertOk();
        }
    }
}
