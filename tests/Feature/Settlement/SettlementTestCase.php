<?php

namespace Tests\Feature\Settlement;

use App\Enums\PeriodMode;
use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\MonthlySnapshot;
use App\Models\Role;
use App\Services\Finance\BazarService;
use App\Services\Finance\DepositService;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use App\Services\Meals\MealEntryService;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Settlement\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Builds small, fully specified months: every amount in a test is chosen so the
 * expected settlement can be worked out by hand.
 */
abstract class SettlementTestCase extends TestCase
{
    use RefreshDatabase;

    protected Mess $mess;

    protected AccountingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->mess, $this->period] = $this->messWithOpenPeriod('2026-11-01', '2026-11-30');
        app(FinanceSetupService::class)->ensureDefaults($this->mess);
    }

    protected function member(string $joined = '2026-11-01'): MessMember
    {
        $member = $this->memberOf($this->mess, Role::MEMBER);
        $member->update(['joined_at' => $joined]);

        return $member->fresh();
    }

    protected function units(MessMember $member, string $units, string $date = '2026-11-05'): void
    {
        app(MealEntryService::class)->saveDailyTotal($this->mess, $member, $date, $units, $this->owner(), approve: true);
    }

    protected function food(string $amount, string $date = '2026-11-02', ?MessMember $paidBy = null): void
    {
        $bazar = app(BazarService::class);
        $entry = $bazar->save($this->mess, [
            'purchase_date' => $date, 'purchased_by' => ($paidBy ?? $this->buyer())->id, 'entry_mode' => 'total',
            'category_id' => $this->mess->bazarCategories()->where('name', 'Rice')->value('id'), 'amount' => $amount,
            'payment_method' => $paidBy ? 'member_paid' : 'cash',
        ], $this->owner());
        $bazar->approve($entry, $this->owner());
    }

    protected function expense(string $amount, string $method = 'equal', array $shares = [], string $category = 'Rent'): void
    {
        $service = app(ExpenseService::class);
        $service->approve($service->save($this->mess, [
            'category_id' => $this->mess->expenseCategories()->where('name', $category)->value('id'), 'title' => $category,
            'amount' => $amount, 'expense_date' => '2026-11-03', 'allocation_method' => $method, 'payment_method' => 'cash', 'shares' => $shares,
        ], $this->owner()), $this->owner());
    }

    protected function deposit(MessMember $member, string $amount, string $date = '2026-11-01'): \App\Models\Deposit
    {
        $service = app(DepositService::class);

        return $service->approve($service->record($this->mess, ['member_id' => $member->id, 'amount' => $amount, 'payment_method' => 'cash', 'paid_at' => $date], $this->owner()), $this->owner());
    }

    protected function finalize(?AccountingPeriod $period = null): MonthlySnapshot
    {
        $period ??= $this->period;
        $period->refresh();
        if ($period->status === PeriodStatus::Open) {
            app(AccountingPeriodService::class)->transition($period, PeriodStatus::Reviewing, $this->owner());
        }

        return app(SettlementService::class)->finalize($period, $this->owner());
    }

    protected function settlementOf(MonthlySnapshot $snapshot, MessMember $member)
    {
        return $snapshot->settlements()->where('member_id', $member->id)->firstOrFail();
    }

    protected function ledgerCredit(MessMember $member): string
    {
        return app(LedgerService::class)->memberCredit($member, app(FinanceSetupService::class)->account($this->mess, FinancialAccount::MEMBER_CREDIT));
    }

    protected function nextPeriod(): AccountingPeriod
    {
        $periods = app(AccountingPeriodService::class);
        $next = $periods->create($this->mess, $this->owner(), PeriodMode::Calendar, '2026-12', null, null);

        return $periods->transition($next, PeriodStatus::Open, $this->owner());
    }

    protected function owner()
    {
        return $this->mess->tenant->owner;
    }

    private ?MessMember $buyer = null;

    private function buyer(): MessMember
    {
        return $this->buyer ??= $this->member();
    }
}
