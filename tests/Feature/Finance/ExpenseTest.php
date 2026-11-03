<?php

namespace Tests\Feature\Finance;

use App\Enums\AllocationMethod;
use App\Models\FinancialAccount;
use App\Models\Role;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function setUpMess(): array
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        app(FinanceSetupService::class)->ensureDefaults($mess);

        return [$mess, $period, $mess->expenseCategories()->pluck('id', 'name')];
    }

    private function base($period, $cats, array $extra = []): array
    {
        return $extra + ['category_id' => $cats['Electricity'], 'title' => 'Electricity', 'amount' => '100', 'expense_date' => $period->start_date->toDateString(), 'allocation_method' => 'equal', 'payment_method' => 'cash'];
    }

    public function test_custom_allocation_must_match_the_amount(): void
    {
        [$mess, $period, $cats] = $this->setUpMess();
        $a = $this->memberOf($mess, Role::MEMBER);
        $b = $this->memberOf($mess, Role::MEMBER);
        $service = app(ExpenseService::class);

        try {
            $service->save($mess, $this->base($period, $cats, ['allocation_method' => 'custom', 'shares' => [$a->id => '60', $b->id => '30']]), $mess->tenant->owner);
            $this->fail('Mismatched custom allocation accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shares', $e->errors());
        }

        $expense = $service->save($mess, $this->base($period, $cats, ['allocation_method' => 'custom', 'shares' => [$a->id => '60.50', $b->id => '39.50']]), $mess->tenant->owner);
        $this->assertSame(['60.50', '39.50'], $expense->allocations()->orderBy('member_id')->pluck('allocated_amount')->all());
    }

    public function test_usage_based_allocation_sums_exactly_with_largest_remainder(): void
    {
        [$mess] = $this->setUpMess();
        $members = collect(range(1, 3))->map(fn () => $this->memberOf($mess, Role::MEMBER));

        $rows = app(ExpenseService::class)->allocations($mess, AllocationMethod::UsageBased, '100.00', $members->mapWithKeys(fn ($m) => [$m->id => '1'])->all());

        $amounts = collect($rows)->pluck('allocated_amount')->sort()->values()->all();
        $this->assertSame(['33.33', '33.33', '33.34'], $amounts);
        $this->assertSame('100.00', bcadd(bcadd($amounts[0], $amounts[1], 2), $amounts[2], 2));

        $weighted = app(ExpenseService::class)->allocations($mess, AllocationMethod::UsageBased, '90.00', [$members[0]->id => '2', $members[1]->id => '1']);
        $this->assertSame(['60.00', '30.00'], collect($weighted)->sortBy('member_id')->pluck('allocated_amount')->values()->all());
    }

    public function test_shares_for_foreign_members_are_rejected(): void
    {
        [$mess, $period, $cats] = $this->setUpMess();
        [$other] = $this->messWithOpenPeriod();
        $foreign = $this->memberOf($other, Role::MEMBER);

        $this->expectException(ValidationException::class);
        app(ExpenseService::class)->save($mess, $this->base($period, $cats, ['allocation_method' => 'custom', 'shares' => [$foreign->id => '100']]), $mess->tenant->owner);
    }

    public function test_food_expenses_must_be_meal_based_and_post_to_food(): void
    {
        [$mess, $period, $cats] = $this->setUpMess();
        $service = app(ExpenseService::class);
        $owner = $mess->tenant->owner;

        try {
            $service->save($mess, $this->base($period, $cats, ['category_id' => $cats['Food (manual)'], 'allocation_method' => 'equal']), $owner);
            $this->fail('Food expense with equal split accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('allocation_method', $e->errors());
        }

        $expense = $service->approve($service->save($mess, $this->base($period, $cats, ['category_id' => $cats['Food (manual)'], 'allocation_method' => 'meal_based', 'amount' => '250']), $owner), $owner);

        $setup = app(FinanceSetupService::class);
        $this->assertSame('250.00', app(LedgerService::class)->accountBalance($setup->account($mess, FinancialAccount::FOOD_EXPENSE)));
        $this->assertNotNull($expense->ledger_transaction_id);
    }

    public function test_void_reverses_an_approved_expense(): void
    {
        [$mess, $period, $cats] = $this->setUpMess();
        $service = app(ExpenseService::class);
        $owner = $mess->tenant->owner;
        $setup = app(FinanceSetupService::class);

        $expense = $service->approve($service->save($mess, $this->base($period, $cats, ['amount' => '1200']), $owner), $owner);
        $this->assertSame('1200.00', app(LedgerService::class)->accountBalance($setup->account($mess, FinancialAccount::OTHER_EXPENSE)));

        $service->void($expense, $owner, 'Wrong month');
        $this->assertSame('0.00', app(LedgerService::class)->accountBalance($setup->account($mess, FinancialAccount::OTHER_EXPENSE)));
    }

    public function test_member_paid_expense_requires_payer(): void
    {
        [$mess, $period, $cats] = $this->setUpMess();

        $this->expectException(ValidationException::class);
        app(ExpenseService::class)->save($mess, $this->base($period, $cats, ['payment_method' => 'member_paid']), $mess->tenant->owner);
    }

    public function test_expense_screens_render(): void
    {
        [$mess, $period, $cats] = $this->setUpMess();
        $owner = $mess->tenant->owner;
        $member = $this->memberOf($mess, Role::MEMBER);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        $this->actingAs($owner)->post("{$base}/expenses", $this->base($period, $cats, ['allocation_method' => 'usage_based', 'shares' => [$member->id => '5']]))->assertSessionHasNoErrors();
        $expense = $mess->expenses()->first();

        foreach (['expenses', 'expenses/create', "expenses/{$expense->id}", "expenses/{$expense->id}/edit", 'finance/settings', 'finance/report', 'ledger', 'ledger/balances', 'deposits', 'bazar', 'bazar/create'] as $path) {
            $this->actingAs($owner)->get("{$base}/{$path}")->assertOk();
        }
    }
}
