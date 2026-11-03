<?php

namespace Tests\Feature\Settlement;

use App\Enums\PeriodStatus;
use App\Models\Role;
use App\Models\SettlementPayment;
use App\Services\Periods\AccountingPeriodService;

class SettlementScreensTest extends SettlementTestCase
{
    public function test_accountant_previews_drafts_finalizes_and_collects_over_http(): void
    {
        $accountant = $this->memberOf($this->mess, Role::ACCOUNTANT);
        $manager = $this->memberOf($this->mess, Role::MANAGER);
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '6');
        $this->units($b, '4');
        $this->food('1000', paidBy: $a);
        $base = "/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}/settlements";

        $this->actingAs($accountant->user)->get("{$base}?period={$this->period->id}")->assertOk()->assertSee('Live preview')->assertSee('100.00');
        $this->actingAs($accountant->user)->post("{$base}/draft", ['period_id' => $this->period->id])->assertSessionHasNoErrors();
        $this->actingAs($accountant->user)->post("{$base}/finalize", ['period_id' => $this->period->id])->assertSessionHasErrors('settlement'); // still open

        app(AccountingPeriodService::class)->transition($this->period, PeriodStatus::Reviewing, $this->owner());
        $this->actingAs($manager->user)->post("{$base}/finalize", ['period_id' => $this->period->id])->assertForbidden();
        $this->actingAs($accountant->user)->post("{$base}/finalize", ['period_id' => $this->period->id])->assertSessionHasNoErrors();
        $this->assertSame('finalized', $this->period->fresh()->status->value);

        $sb = $this->period->settlements()->where('member_id', $b->id)->first();
        $this->assertSame('-400.00', $sb->closing_credit);

        $payload = ['amount' => '400', 'payment_method' => 'cash', 'idempotency_key' => 'pay-1'];
        $this->actingAs($accountant->user)->post("{$base}/{$sb->id}/payments", $payload)->assertSessionHasNoErrors();
        $this->actingAs($accountant->user)->post("{$base}/{$sb->id}/payments", $payload)->assertSessionHas('status', 'That payment was already recorded.');
        $this->assertSame(1, SettlementPayment::count());
        $this->assertSame('paid', $sb->fresh()->status);

        $this->actingAs($accountant->user)->get("{$base}?period={$this->period->id}")->assertOk()->assertSee('Final v');
    }

    public function test_members_see_only_their_own_statement(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '1');
        $this->units($b, '1');
        $this->food('100', paidBy: $a);
        $snapshot = $this->finalize();
        $base = "/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}";

        $own = $this->settlementOf($snapshot, $a);
        $other = $this->settlementOf($snapshot, $b);

        $this->actingAs($a->user)->get("{$base}/settlements/{$own->id}")->assertOk()->assertSee('Mess owes the member');
        $this->actingAs($a->user)->get("{$base}/settlements/{$other->id}")->assertForbidden();
        $this->actingAs($a->user)->get("{$base}/settlements")->assertForbidden();
        $this->actingAs($a->user)->post("{$base}/settlements/{$own->id}/payments", ['amount' => '1', 'payment_method' => 'cash'])->assertForbidden();
        $this->actingAs($a->user)->get("{$base}/members/{$a->id}")->assertOk()->assertSee($this->period->name);
    }

    public function test_reopen_via_period_screen_supersedes_settlement(): void
    {
        $a = $this->member();
        $this->units($a, '2');
        $this->food('100', paidBy: $a);
        $snapshot = $this->finalize();
        $admin = $this->memberOf($this->mess, Role::MESS_ADMIN);

        $this->actingAs($admin->user)->post("/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}/periods/{$this->period->id}/reopen", ['reason' => 'Late receipt found'])
            ->assertSessionHasNoErrors();

        $this->assertSame('superseded', $snapshot->fresh()->status);
        $this->assertSame('open', $this->period->fresh()->status->value);
    }

    public function test_settlement_of_another_mess_is_not_reachable(): void
    {
        $a = $this->member();
        $this->units($a, '1');
        $this->food('50', paidBy: $a);
        $foreign = $this->settlementOf($this->finalize(), $a);

        [$other] = $this->messWithOpenPeriod();
        $base = "/t/{$other->tenant->slug}/m/{$other->slug}";

        $this->actingAs($other->tenant->owner)->get("{$base}/settlements/{$foreign->id}")->assertNotFound();
        $this->actingAs($other->tenant->owner)->post("{$base}/settlements/{$foreign->id}/payments", ['amount' => '1', 'payment_method' => 'cash'])->assertNotFound();
    }

    public function test_adjustment_and_settings_screens(): void
    {
        $a = $this->member();
        $base = "/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}";
        $owner = $this->owner();

        $this->actingAs($owner)->post("{$base}/finance/adjustments", ['period_id' => $this->period->id, 'member_id' => $a->id, 'adjustment_type' => 'debit', 'amount' => '75', 'reason' => 'Broken chair', 'approve' => '1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->get("{$base}/finance/adjustments")->assertOk()->assertSee('Broken chair');

        $this->actingAs($owner)->put("{$base}/settlements/settings", ['strategy' => 'custom', 'equal_split_basis' => 'members'])->assertSessionHasErrors('custom_rate');
        $this->actingAs($owner)->put("{$base}/settlements/settings", ['strategy' => 'custom', 'custom_rate' => '80', 'equal_split_basis' => 'days'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get("{$base}/settlements")->assertOk()->assertSee('custom');
        $this->actingAs($a->user)->get("{$base}/finance/adjustments")->assertForbidden();
    }
}
