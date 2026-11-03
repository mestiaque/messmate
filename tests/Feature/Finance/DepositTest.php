<?php

namespace Tests\Feature\Finance;

use App\Models\Deposit;
use App\Models\FinancialAccount;
use App\Models\Role;
use App\Services\Finance\DepositService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_deposit_posts_to_cash_and_member_credit(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $service = app(DepositService::class);
        $owner = $mess->tenant->owner;

        $deposit = $service->record($mess, ['member_id' => $member->id, 'amount' => '2500', 'payment_method' => 'cash', 'paid_at' => $period->start_date->toDateString()], $owner);
        $this->assertSame('pending', $deposit->status->value);
        $this->assertNull($deposit->ledger_transaction_id);

        $service->approve($deposit, $owner);
        $service->approve($service->record($mess, ['member_id' => $member->id, 'amount' => '500', 'payment_method' => 'mobile_wallet', 'paid_at' => $period->start_date->toDateString(), 'transaction_reference' => 'BK9'], $owner), $owner);

        $setup = app(FinanceSetupService::class);
        $ledger = app(LedgerService::class);
        $this->assertSame('2500.00', $ledger->accountBalance($setup->account($mess, FinancialAccount::CASH)));
        $this->assertSame('500.00', $ledger->accountBalance($setup->account($mess, FinancialAccount::MOBILE_WALLET)));
        $this->assertSame('3000.00', $ledger->memberCredit($member, $setup->account($mess, FinancialAccount::MEMBER_CREDIT)));

        try {
            $service->approve($deposit->fresh(), $owner);
            $this->fail('Deposit approved twice.');
        } catch (ValidationException) {
        }
    }

    public function test_reversal_restores_balances(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $service = app(DepositService::class);
        $owner = $mess->tenant->owner;
        $deposit = $service->approve($service->record($mess, ['member_id' => $member->id, 'amount' => '1000', 'payment_method' => 'cash', 'paid_at' => $period->start_date->toDateString()], $owner), $owner);

        $service->reverse($deposit, $owner, 'Bounced');

        $setup = app(FinanceSetupService::class);
        $this->assertSame('reversed', $deposit->fresh()->status->value);
        $this->assertSame('0.00', app(LedgerService::class)->memberCredit($member, $setup->account($mess, FinancialAccount::MEMBER_CREDIT)));
        $this->assertSame('0.00', app(LedgerService::class)->accountBalance($setup->account($mess, FinancialAccount::CASH)));
    }

    public function test_duplicate_transaction_reference_is_rejected(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $data = ['member_id' => $member->id, 'amount' => '500', 'payment_method' => 'mobile_wallet', 'paid_at' => $period->start_date->toDateString(), 'transaction_reference' => 'BK777'];
        $service = app(DepositService::class);

        $service->record($mess, $data, $mess->tenant->owner);

        try {
            $service->record($mess, $data, $mess->tenant->owner);
            $this->fail('Duplicate reference accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transaction_reference', $e->errors());
        }

        // The same reference via a different method, or in another mess, is fine.
        $service->record($mess, ['payment_method' => 'bank'] + $data, $mess->tenant->owner);
        [$other, $otherPeriod] = $this->messWithOpenPeriod();
        $service->record($other, ['member_id' => $this->memberOf($other, Role::MEMBER)->id, 'paid_at' => $otherPeriod->start_date->toDateString()] + $data, $other->tenant->owner);
        $this->assertSame(3, Deposit::count());
    }

    public function test_http_double_submit_creates_one_deposit_and_members_cannot_record(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $accountant = $this->memberOf($mess, Role::ACCOUNTANT);
        $url = "/t/{$mess->tenant->slug}/m/{$mess->slug}/deposits";
        $payload = ['member_id' => $member->id, 'amount' => '1500', 'payment_method' => 'cash', 'paid_at' => $period->start_date->toDateString(), 'approve' => '1', 'idempotency_key' => 'k-1'];

        $this->actingAs($member->user)->post($url, $payload)->assertForbidden();
        $this->actingAs($accountant->user)->post($url, $payload)->assertSessionHasNoErrors();
        $this->actingAs($accountant->user)->post($url, $payload)->assertSessionHas('status', 'That deposit was already saved.');
        $this->actingAs($accountant->user)->post($url, ['amount' => '9999'] + $payload)->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Deposit::count());
        $this->assertSame('approved', Deposit::first()->status->value);
        $this->actingAs($accountant->user)->get($url)->assertOk()->assertSee('1,500.00');
    }

    public function test_bank_deposit_needs_reference_and_foreign_member_is_rejected(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        [$other] = $this->messWithOpenPeriod();
        $foreign = $this->memberOf($other, Role::MEMBER);
        $url = "/t/{$mess->tenant->slug}/m/{$mess->slug}/deposits";
        $owner = $mess->tenant->owner;

        $this->actingAs($owner)->post($url, ['member_id' => $this->memberOf($mess, Role::MEMBER)->id, 'amount' => '100', 'payment_method' => 'bank', 'paid_at' => $period->start_date->toDateString()])
            ->assertSessionHasErrors('transaction_reference');
        $this->actingAs($owner)->post($url, ['member_id' => $foreign->id, 'amount' => '100', 'payment_method' => 'cash', 'paid_at' => $period->start_date->toDateString()])
            ->assertSessionHasErrors('member_id');
        $this->assertSame(0, Deposit::count());
    }
}
