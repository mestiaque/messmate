<?php

namespace Tests\Feature\Settlement;

use App\Enums\MembershipStatus;
use App\Services\Settlement\SettlementPolicy;
use App\Services\Settlement\SettlementService;
use App\Services\Settlement\SettlementSettingsService;
use App\Services\Tenancy\MembershipService;

class AllocationTest extends SettlementTestCase
{
    public function test_equal_allocation_among_members(): void
    {
        $a = $this->member();
        $b = $this->member();
        $c = $this->member();
        $this->expense('1000');

        $rows = app(SettlementService::class)->calculate($this->period)['members'];

        // The extra paisa goes to one member (by member-code order); the parts always sum exactly.
        $shares = collect([$a, $b, $c])->map(fn ($m) => $rows[$m->id]['other_expense'])->sort()->values()->all();
        $this->assertSame(['333.33', '333.33', '333.34'], $shares);
    }

    public function test_meal_based_allocation(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '30');
        $this->units($b, '10');
        $this->expense('400', 'meal_based', category: 'Gas');

        $rows = app(SettlementService::class)->calculate($this->period)['members'];

        $this->assertSame('300.00', $rows[$a->id]['other_expense']);
        $this->assertSame('100.00', $rows[$b->id]['other_expense']);
    }

    public function test_custom_and_usage_allocations_use_saved_shares(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->expense('1000', 'custom', [$a->id => '700', $b->id => '300'], 'Internet');
        $this->expense('90', 'usage_based', [$a->id => '2', $b->id => '1'], 'Water');

        $rows = app(SettlementService::class)->calculate($this->period)['members'];

        $this->assertSame('760.00', $rows[$a->id]['other_expense']);
        $this->assertSame('330.00', $rows[$b->id]['other_expense']);
    }

    public function test_excluded_expenses_are_listed_but_not_charged(): void
    {
        $a = $this->member();
        $this->expense('5000', 'excluded', category: 'Maintenance');

        $result = app(SettlementService::class)->calculate($this->period);

        $this->assertSame('0.00', $result['members'][$a->id]['other_expense']);
        $this->assertSame('5000.00', $result['other_expenses']['excluded'][0]['amount']);
    }

    public function test_mid_period_joining_and_leaving_with_day_based_split(): void
    {
        $full = $this->member('2026-11-01');
        $joiner = $this->member('2026-11-16');      // 15 of 30 days
        $leaver = $this->member('2026-11-01');
        app(MembershipService::class)->changeStatus($leaver, MembershipStatus::Left, '2026-11-10', $this->owner()); // 10 days
        $notYet = $this->member('2026-12-05');       // not in this period at all
        $this->expense('1100');
        app(SettlementSettingsService::class)->save($this->mess, new SettlementPolicy('weighted', null, 'days'));

        $rows = app(SettlementService::class)->calculate($this->period)['members'];

        $this->assertSame('600.00', $rows[$full->id]['other_expense']);   // 30 / 55
        $this->assertSame('300.00', $rows[$joiner->id]['other_expense']); // 15 / 55
        $this->assertSame('200.00', $rows[$leaver->id]['other_expense']); // 10 / 55
        $this->assertArrayNotHasKey($notYet->id, $rows);

        app(SettlementSettingsService::class)->save($this->mess, new SettlementPolicy());
        $equal = app(SettlementService::class)->calculate($this->period)['members'];
        $equalShares = collect([$full, $joiner, $leaver])->map(fn ($m) => $equal[$m->id]['other_expense'])->sort()->values()->all();
        $this->assertSame(['366.66', '366.67', '366.67'], $equalShares);
    }

    public function test_bazar_non_food_items_are_shared_equally(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '1');
        $bazar = app(\App\Services\Finance\BazarService::class);
        $bazar->approve($bazar->save($this->mess, [
            'purchase_date' => '2026-11-02', 'purchased_by' => $a->id, 'entry_mode' => 'total', 'payment_method' => 'cash',
            'category_id' => $this->mess->bazarCategories()->where('name', 'Cleaning supplies')->value('id'), 'amount' => '200',
        ], $this->owner()), $this->owner());

        $result = app(SettlementService::class)->calculate($this->period);

        $this->assertSame('0.00', $result['food_pool']['total']);
        $this->assertSame('100.00', $result['members'][$b->id]['other_expense']);
    }
}
