<?php

namespace Tests\Feature\Settlement;

use App\Enums\PaymentMethod;
use App\Enums\PeriodStatus;
use App\Models\FinancialAccount;
use App\Models\MonthlySnapshot;
use App\Services\Finance\BazarService;
use App\Services\Finance\DepositService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Services\Settlement\FinancialAdjustmentService;
use App\Services\Settlement\SettlementPaymentService;
use App\Services\Settlement\SettlementService;
use Illuminate\Validation\ValidationException;
use LogicException;

class SettlementLifecycleTest extends SettlementTestCase
{
    public function test_multiple_deposits_positive_balance_and_partial_then_full_refund(): void
    {
        $a = $this->member();
        $this->units($a, '10');
        $this->food('600', paidBy: $a);
        $this->deposit($a, '300');
        $this->deposit($a, '200', '2026-11-15');

        $s = $this->settlementOf($this->finalize(), $a);
        // 600 paid + 500 deposits − 600 meals
        $this->assertSame('500.00', $s->total_deposits);
        $this->assertSame('500.00', $s->closing_credit);

        $payments = app(SettlementPaymentService::class);
        $payments->record($s, '200', PaymentMethod::Cash, null, $this->owner());
        $this->assertSame('partially_paid', $s->fresh()->status);

        try {
            $payments->record($s->fresh(), '400', PaymentMethod::Cash, null, $this->owner());
            $this->fail('Over-refund accepted.');
        } catch (ValidationException) {
        }

        $payments->record($s->fresh(), '300', PaymentMethod::Cash, null, $this->owner());
        $this->assertSame('paid', $s->fresh()->status);
        $this->assertSame('0.00', $this->ledgerCredit($a));
        $this->assertSame('refund', $s->payments()->first()->payment_type);
    }

    public function test_negative_balance_is_collected(): void
    {
        $a = $this->member();
        $this->units($a, '10');
        $this->food('800');

        $snapshot = $this->finalize();
        $s = $this->settlementOf($snapshot, $a);
        $this->assertSame('-800.00', $s->closing_credit);

        app(SettlementPaymentService::class)->record($s, '800', PaymentMethod::MobileWallet, 'BK1', $this->owner());

        $this->assertSame('paid', $s->fresh()->status);
        $this->assertSame('collection', $s->payments()->first()->payment_type);
        $this->assertSame('0.00', $this->ledgerCredit($a));
    }

    public function test_opening_credit_carries_forward_including_payments(): void
    {
        $a = $this->member();
        $this->units($a, '10');
        $this->food('700', paidBy: $a);
        $this->deposit($a, '1000');
        $first = $this->settlementOf($this->finalize(), $a);
        $this->assertSame('1000.00', $first->closing_credit);

        // Partly refunded during December.
        $next = $this->nextPeriod();
        $this->travelTo('2026-12-03 10:00:00');
        app(SettlementPaymentService::class)->record($first, '400', PaymentMethod::Cash, null, $this->owner());

        $this->units($a, '5', '2026-12-04');
        $this->food('250', '2026-12-04', $a);

        $second = $this->settlementOf($this->finalize($next), $a);

        $this->assertSame('1000.00', $second->opening_credit);
        $this->assertSame('250.00', $second->other_credits); // December purchase paid by A
        $this->assertSame('400.00', $second->other_debits);  // refund paid in December
        $this->assertSame('600.00', $second->closing_credit); // 1000 + 250 paid − 400 refund − 250 meals
        $this->assertSame('600.00', $this->ledgerCredit($a));
    }

    public function test_financial_adjustments_change_the_balance(): void
    {
        $a = $this->member();
        $service = app(FinancialAdjustmentService::class);
        $service->create($this->period, $a, 'credit', '150', 'Fixed the tap', $this->owner(), approve: true);
        $service->create($this->period, $a, 'debit', '50', 'Late fee', $this->owner(), approve: true);

        $s = $this->settlementOf($this->finalize(), $a);

        $this->assertSame('150.00', $s->other_credits);
        $this->assertSame('50.00', $s->other_debits);
        $this->assertSame('100.00', $s->closing_credit);
    }

    public function test_reversed_and_voided_transactions_are_excluded(): void
    {
        $a = $this->member();
        $this->units($a, '10');
        $this->food('500', paidBy: $a);
        $deposit = $this->deposit($a, '900');
        app(DepositService::class)->reverse($deposit, $this->owner(), 'Bounced');

        $bazar = app(BazarService::class);
        $wrong = $bazar->save($this->mess, ['purchase_date' => '2026-11-07', 'purchased_by' => $a->id, 'entry_mode' => 'total', 'payment_method' => 'cash',
            'category_id' => $this->mess->bazarCategories()->where('name', 'Fish')->value('id'), 'amount' => '999'], $this->owner());
        $bazar->void($bazar->approve($wrong, $this->owner()), $this->owner(), 'Typo');

        $snapshot = $this->finalize();

        $this->assertSame('500.00', $snapshot->total_food_expense);
        $this->assertSame('0.00', $this->settlementOf($snapshot, $a)->total_deposits);
        $this->assertSame('0.00', $this->settlementOf($snapshot, $a)->closing_credit);
    }

    public function test_duplicate_settlement_generation_is_prevented(): void
    {
        $a = $this->member();
        $this->units($a, '1');
        $this->food('100', paidBy: $a);
        $service = app(SettlementService::class);

        $service->generateDraft($this->period, $this->owner());
        $service->generateDraft($this->period, $this->owner());
        $this->assertSame(1, $this->period->snapshots()->count());

        $this->finalize();
        $this->assertSame(1, $this->period->snapshots()->count());
        $this->assertSame('final', $this->period->snapshots()->first()->status);

        foreach ([fn () => $service->generateDraft($this->period->fresh(), $this->owner()), fn () => $service->finalize($this->period->fresh(), $this->owner())] as $attempt) {
            try {
                $attempt();
                $this->fail('A second settlement was generated.');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(1, $this->period->snapshots()->count());
        $this->assertSame(1, $this->period->settlements()->count());
    }

    public function test_pending_items_block_finalization(): void
    {
        $a = $this->member();
        app(DepositService::class)->record($this->mess, ['member_id' => $a->id, 'amount' => '100', 'payment_method' => 'cash', 'paid_at' => '2026-11-02'], $this->owner());

        $this->expectException(ValidationException::class);
        $this->finalize();
    }

    public function test_reopening_supersedes_reverses_and_allows_a_new_version(): void
    {
        $a = $this->member();
        $this->units($a, '10');
        $this->food('1000', paidBy: $a);
        $first = $this->finalize();
        $this->assertSame('0.00', $this->ledgerCredit($a));
        $this->assertSame(PeriodStatus::Finalized, $this->period->fresh()->status);

        try {
            $first->update(['calculated_meal_rate' => '1']);
            $this->fail('Final snapshot was edited.');
        } catch (LogicException) {
        }

        app(SettlementService::class)->reopen($this->period, $this->owner(), 'Missed a bazar receipt');

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('superseded', $first->settlements()->first()->status);
        $this->assertSame('1000.00', $this->ledgerCredit($a)); // charges reversed
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement.superseded']);

        $this->food('200', '2026-11-20', $a);
        $second = $this->finalize();

        $this->assertSame(2, $second->calculation_version);
        $this->assertSame('120.000000', $second->calculated_meal_rate);
        $this->assertSame('100.000000', $first->fresh()->calculated_meal_rate); // superseded version is kept unchanged
        $this->assertSame('0.00', $this->ledgerCredit($a));
        $totals = app(LedgerService::class)->totals($this->mess);
        $this->assertSame($totals['debit'], $totals['credit']);
    }

    public function test_reconciliation_failure_rolls_everything_back(): void
    {
        $a = $this->member();
        $this->deposit($a, '100');
        // A posting on the member's balance with no source document.
        $setup = app(FinanceSetupService::class);
        app(LedgerService::class)->post($this->mess, '2026-11-09', 'Stray entry', [
            ['account' => $setup->account($this->mess, FinancialAccount::CASH), 'debit' => '25'],
            ['account' => $setup->account($this->mess, FinancialAccount::MEMBER_CREDIT), 'member' => $a, 'credit' => '25'],
        ], $this->owner());

        try {
            $this->finalize();
            $this->fail('Unreconciled settlement was finalized.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Reconciliation failed', implode(' ', $e->errors()['settlement']));
        }

        $this->assertSame(0, MonthlySnapshot::count());
        $this->assertSame('reviewing', $this->period->fresh()->status->value);
    }

    public function test_snapshot_reproduces_the_settlement(): void
    {
        $a = $this->member();
        $this->units($a, '3');
        $this->food('90', paidBy: $a);

        $snapshot = $this->finalize();
        $details = $snapshot->calculation_details;

        $this->assertSame('weighted', $details['strategy']);
        $this->assertSame('90.00', $details['food_pool']['total']);
        $this->assertSame($snapshot->settlements()->first()->closing_credit, $details['members'][$a->id]['closing_credit']);
        $this->assertSame('v1', $details['engine']);
    }

    public function test_period_cannot_be_finalized_without_settlement(): void
    {
        app(\App\Services\Periods\AccountingPeriodService::class)->transition($this->period, PeriodStatus::Reviewing, $this->owner());

        $this->expectException(ValidationException::class);
        app(\App\Services\Periods\AccountingPeriodService::class)->transition($this->period->fresh(), PeriodStatus::Finalized, $this->owner());
    }
}
