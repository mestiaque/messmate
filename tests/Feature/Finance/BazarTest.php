<?php

namespace Tests\Feature\Finance;

use App\Models\BazarEntry;
use App\Models\FinancialAccount;
use App\Models\Role;
use App\Services\Finance\BazarService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BazarTest extends TestCase
{
    use RefreshDatabase;

    private function setUpMess(): array
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        app(FinanceSetupService::class)->ensureDefaults($mess);
        $buyer = $this->memberOf($mess, Role::BAZAR_MANAGER);
        $cats = $mess->bazarCategories()->pluck('id', 'name');

        return [$mess, $period, $buyer, $cats];
    }

    private function itemized($period, $buyer, $cats, array $extra = []): array
    {
        return $extra + [
            'purchase_date' => $period->start_date->toDateString(), 'purchased_by' => $buyer->id, 'entry_mode' => 'itemized',
            'payment_method' => 'cash', 'discount' => '20',
            'items' => [
                ['item_name' => 'Rice', 'category_id' => $cats['Rice'], 'quantity' => '2.5', 'unit' => 'kg', 'unit_price' => '68', 'discount' => '0'],
                ['item_name' => 'Fish', 'category_id' => $cats['Fish'], 'quantity' => '1.25', 'unit' => 'kg', 'unit_price' => '320', 'discount' => '10'],
                ['item_name' => 'Soap', 'category_id' => $cats['Cleaning supplies'], 'quantity' => '2', 'unit' => 'pcs', 'unit_price' => '35'],
            ],
            // The browser cannot dictate totals: these are ignored.
            'subtotal' => '1', 'total_amount' => '1', 'food_amount' => '1',
        ];
    }

    public function test_itemized_totals_and_food_share_are_computed_on_the_server(): void
    {
        [$mess, $period, $buyer, $cats] = $this->setUpMess();

        $entry = app(BazarService::class)->save($mess, $this->itemized($period, $buyer, $cats), $mess->tenant->owner);

        // 170 + (400 - 10) + 70 = 630; minus 20 = 610. Food subtotal 560 → 560 × 610 / 630 = 542.22
        $this->assertSame('630.00', $entry->subtotal);
        $this->assertSame('610.00', $entry->total_amount);
        $this->assertSame('542.22', $entry->food_amount);
        $this->assertSame(['170.00', '390.00', '70.00'], $entry->items()->orderBy('id')->pluck('total')->all());
    }

    public function test_total_mode_uses_category_and_invalid_discounts_are_rejected(): void
    {
        [$mess, $period, $buyer, $cats] = $this->setUpMess();
        $service = app(BazarService::class);
        $base = ['purchase_date' => $period->start_date->toDateString(), 'purchased_by' => $buyer->id, 'entry_mode' => 'total', 'payment_method' => 'cash'];

        $food = $service->save($mess, $base + ['category_id' => $cats['Vegetables'], 'amount' => '640'], $mess->tenant->owner);
        $nonFood = $service->save($mess, $base + ['category_id' => $cats['Gas cylinder'], 'amount' => '1450'], $mess->tenant->owner);

        $this->assertSame('640.00', $food->food_amount);
        $this->assertSame('0.00', $nonFood->food_amount);

        $this->expectException(ValidationException::class);
        $service->save($mess, $base + ['category_id' => $cats['Rice'], 'amount' => '100', 'discount' => '150'], $mess->tenant->owner);
    }

    public function test_approval_posts_split_debits_and_void_reverses_them(): void
    {
        [$mess, $period, $buyer, $cats] = $this->setUpMess();
        $service = app(BazarService::class);
        $ledger = app(LedgerService::class);
        $setup = app(FinanceSetupService::class);
        $owner = $mess->tenant->owner;

        $entry = $service->approve($service->save($mess, $this->itemized($period, $buyer, $cats), $owner), $owner);

        $this->assertSame('542.22', $ledger->accountBalance($setup->account($mess, FinancialAccount::FOOD_EXPENSE)));
        $this->assertSame('67.78', $ledger->accountBalance($setup->account($mess, FinancialAccount::OTHER_EXPENSE)));
        $this->assertSame('-610.00', $ledger->accountBalance($setup->account($mess, FinancialAccount::CASH)));

        try {
            $service->save($mess, $this->itemized($period, $buyer, $cats), $owner, $entry);
            $this->fail('Approved purchase was edited.');
        } catch (ValidationException) {
        }

        $service->void($entry, $owner, 'Duplicate');

        $this->assertSame('voided', $entry->fresh()->status->value);
        $this->assertSame('0.00', $ledger->accountBalance($setup->account($mess, FinancialAccount::FOOD_EXPENSE)));
        $this->assertSame('0.00', $ledger->accountBalance($setup->account($mess, FinancialAccount::CASH)));
        $totals = $ledger->totals($mess);
        $this->assertSame($totals['debit'], $totals['credit']);
    }

    public function test_member_paid_purchase_credits_the_purchaser(): void
    {
        [$mess, $period, $buyer, $cats] = $this->setUpMess();
        $service = app(BazarService::class);
        $setup = app(FinanceSetupService::class);

        $service->approve($service->save($mess, $this->itemized($period, $buyer, $cats, ['payment_method' => 'member_paid']), $mess->tenant->owner), $mess->tenant->owner);

        $this->assertSame('610.00', app(LedgerService::class)->memberCredit($buyer, $setup->account($mess, FinancialAccount::MEMBER_CREDIT)));
    }

    public function test_purchases_in_closed_periods_are_rejected(): void
    {
        [$mess, $period, $buyer, $cats] = $this->setUpMess();
        $period->update(['status' => 'finalized']);

        $this->expectException(ValidationException::class);
        app(BazarService::class)->save($mess, $this->itemized($period, $buyer, $cats), $mess->tenant->owner);
    }

    public function test_http_flow_respects_roles_idempotency_and_receipt_privacy(): void
    {
        Storage::fake('local');
        [$mess, $period, $buyer, $cats] = $this->setUpMess();
        $accountant = $this->memberOf($mess, Role::ACCOUNTANT);
        $member = $this->memberOf($mess, Role::MEMBER);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}/bazar";
        $payload = $this->itemized($period, $buyer, $cats, ['idempotency_key' => 'form-123', 'receipt' => UploadedFile::fake()->image('memo.jpg')]);

        $this->actingAs($member->user)->post($base, $payload)->assertForbidden();
        $this->actingAs($buyer->user)->post($base, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($buyer->user)->post($base, $payload)->assertRedirect()->assertSessionHas('status', 'That purchase was already saved.');
        $this->assertSame(1, BazarEntry::count());

        $entry = BazarEntry::first();
        Storage::disk('local')->assertExists($entry->receipt_path);
        $this->assertStringStartsWith("receipts/{$mess->tenant_id}/{$mess->id}/", $entry->receipt_path);

        $this->actingAs($buyer->user)->post("{$base}/{$entry->id}/decide", ['action' => 'approve'])->assertForbidden();
        $this->actingAs($accountant->user)->post("{$base}/{$entry->id}/decide", ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $entry->fresh()->status->value);

        $receipt = "/t/{$mess->tenant->slug}/m/{$mess->slug}/receipts/bazar/{$entry->id}";
        $this->actingAs($accountant->user)->get($receipt)->assertOk();
        $this->actingAs($member->user)->get($receipt)->assertForbidden();

        foreach (['', '/create', "/{$entry->id}"] as $path) {
            $this->actingAs($accountant->user)->get($base.$path)->assertStatus($path === '/create' ? 403 : 200);
            $this->actingAs($buyer->user)->get($base.$path)->assertOk();
        }
        $this->actingAs($buyer->user)->get("{$base}/{$entry->id}/edit")->assertRedirect();
    }

    public function test_purchase_of_another_mess_is_not_reachable(): void
    {
        [$mess] = $this->setUpMess();
        [$other, $otherPeriod, $otherBuyer, $otherCats] = $this->setUpMess();
        $foreign = app(BazarService::class)->save($other, $this->itemized($otherPeriod, $otherBuyer, $otherCats), $other->tenant->owner);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        $this->actingAs($mess->tenant->owner)->get("{$base}/bazar/{$foreign->id}")->assertNotFound();
        $this->actingAs($mess->tenant->owner)->post("{$base}/bazar/{$foreign->id}/decide", ['action' => 'approve'])->assertNotFound();
        $this->actingAs($mess->tenant->owner)->get("{$base}/receipts/bazar/{$foreign->id}")->assertNotFound();
        $this->assertSame('submitted', $foreign->fresh()->status->value);
    }

    public function test_purchaser_from_another_mess_is_rejected(): void
    {
        [$mess, $period, , $cats] = $this->setUpMess();
        [, , $foreignBuyer] = $this->setUpMess();

        $this->expectException(ValidationException::class);
        app(BazarService::class)->save($mess, $this->itemized($period, $foreignBuyer, $cats), $mess->tenant->owner);
    }
}
