<?php

namespace Tests\Feature\Settlement;

use App\Models\GuestMeal;
use App\Models\MealType;
use App\Services\Meals\GuestMealService;
use App\Services\Meals\MealEntryService;
use App\Services\Settlement\SettlementPolicy;
use App\Services\Settlement\SettlementService;
use App\Services\Settlement\SettlementSettingsService;
use Illuminate\Validation\ValidationException;

class CalculationTest extends SettlementTestCase
{
    public function test_specification_example_rate_75_and_closing_1225(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '45');
        $this->units($b, '155');
        $this->food('15000');
        $this->expense('900'); // equal: 300 each (the bazar buyer is a third, meal-less member)
        $this->deposit($a, '5000');

        $snapshot = $this->finalize();
        $sa = $this->settlementOf($snapshot, $a);

        $this->assertSame('75.000000', $snapshot->calculated_meal_rate);
        $this->assertSame('3375.00', $sa->meal_cost);
        $this->assertSame('300.00', $sa->other_expense);
        $this->assertSame('1325.00', $sa->closing_credit);
        $this->assertSame('-11925.00', $this->settlementOf($snapshot, $b)->closing_credit);
    }

    public function test_exact_spec_numbers_with_two_members(): void
    {
        // Same as the spec example but the purchase is paid by B, so only A and B are members.
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '45');
        $this->units($b, '155');
        $this->food('15000', paidBy: $b);
        $this->expense('800');
        $this->deposit($a, '5000');

        $snapshot = $this->finalize();
        $sa = $this->settlementOf($snapshot, $a);
        $sb = $this->settlementOf($snapshot, $b);

        $this->assertSame('3375.00', $sa->meal_cost);
        $this->assertSame('400.00', $sa->other_expense);
        $this->assertSame('1225.00', $sa->closing_credit); // positive: mess owes A
        $this->assertSame('15000.00', $sb->other_credits);  // B paid the bazar
        $this->assertSame('2975.00', $sb->closing_credit);  // 15000 − 11625 − 400
        $this->assertSame('1225.00', $this->ledgerCredit($a));
        $this->assertSame('2975.00', $this->ledgerCredit($b));
    }

    public function test_zero_meals_and_zero_food(): void
    {
        $a = $this->member();
        $this->deposit($a, '1000');

        $snapshot = $this->finalize();

        $this->assertSame('0.000000', $snapshot->calculated_meal_rate);
        $this->assertSame('1000.00', $this->settlementOf($snapshot, $a)->closing_credit);
    }

    public function test_food_cost_without_meals_cannot_be_finalized(): void
    {
        $this->member();
        $this->food('500');

        try {
            $this->finalize();
            $this->fail('Finalized food cost with zero meals.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no meals', implode(' ', $e->errors()['settlement']));
        }

        $this->assertSame('reviewing', $this->period->fresh()->status->value);
        $this->assertSame(0, $this->period->snapshots()->count());
    }

    public function test_zero_food_with_meals_costs_nothing(): void
    {
        $a = $this->member();
        $this->units($a, '10');

        $snapshot = $this->finalize();

        $this->assertSame('0.00', $this->settlementOf($snapshot, $a)->meal_cost);
    }

    public function test_meal_factors_and_both_tracking_modes(): void
    {
        $a = $this->member();
        $b = $this->member();
        $types = MealType::where('mess_id', $this->mess->id)->pluck('id', 'code');
        $meals = app(MealEntryService::class);
        foreach (['2026-11-05', '2026-11-06'] as $date) {
            $meals->saveIndividual($this->mess, $a, $date, [$types['breakfast'] => 1, $types['lunch'] => 1, $types['dinner'] => 1], $this->owner(), approve: true);
        }
        $meals->saveDailyTotal($this->mess, $b, '2026-11-05', null, $this->owner(), approve: true, rawCount: '4', factor: '0.75');
        $this->food('800', paidBy: $a);

        $snapshot = $this->finalize();

        $this->assertSame('8.0000', $snapshot->total_weighted_meals); // 2.5 × 2 + 3
        $this->assertSame('100.000000', $snapshot->calculated_meal_rate);
        $this->assertSame('500.00', $this->settlementOf($snapshot, $a)->meal_cost);
        $this->assertSame('300.00', $this->settlementOf($snapshot, $b)->meal_cost);
    }

    public function test_guest_meals_go_to_sponsor_and_unsponsored_are_shared(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '4');
        $this->units($b, '3');
        $guests = app(GuestMealService::class);
        $guests->create($this->mess, ['guest_name' => 'Cousin', 'meal_date' => '2026-11-05', 'quantity' => '2', 'sponsor_member_id' => $a->id], $this->owner(), approve: true);
        $guests->create($this->mess, ['guest_name' => 'Visitor', 'meal_date' => '2026-11-05', 'quantity' => '1'], $this->owner(), approve: true);
        $this->food('1000', paidBy: $a);

        $snapshot = $this->finalize();
        $sa = $this->settlementOf($snapshot, $a);
        $sb = $this->settlementOf($snapshot, $b);

        $this->assertSame('100.000000', $snapshot->calculated_meal_rate); // 1000 / (4 + 3 + 2 + 1)
        $this->assertSame('400.00', $sa->meal_cost);
        $this->assertSame('250.00', $sa->guest_cost); // 200 sponsored + 50 share of the unsponsored guest
        $this->assertSame('50.00', $sb->guest_cost);
        $this->assertSame('1000.00', bcadd(bcadd($sa->meal_cost, $sa->guest_cost, 2), bcadd($sb->meal_cost, $sb->guest_cost, 2), 2));
        $this->assertSame(2, GuestMeal::count());
    }

    public function test_decimal_rounding_shares_add_up_to_the_pool(): void
    {
        $members = [$this->member(), $this->member(), $this->member()];
        foreach ($members as $m) {
            $this->units($m, '1');
        }
        $this->food('100', paidBy: $members[0]);

        $snapshot = $this->finalize();
        $costs = collect($members)->map(fn ($m) => $this->settlementOf($snapshot, $m)->meal_cost)->sort()->values()->all();

        $this->assertSame('33.333333', $snapshot->calculated_meal_rate);
        $this->assertSame(['33.33', '33.33', '33.34'], $costs);
    }

    public function test_equal_and_custom_strategies(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '9');
        $this->units($b, '1');
        $this->food('1000', paidBy: $a);
        $settings = app(SettlementSettingsService::class);

        $settings->save($this->mess, new SettlementPolicy('equal'));
        $equal = app(SettlementService::class)->calculate($this->period);
        $this->assertSame('500.00', $equal['members'][$b->id]['meal_cost']);

        $settings->save($this->mess, new SettlementPolicy('custom', '90'));
        $custom = app(SettlementService::class)->calculate($this->period);
        $this->assertSame('810.00', $custom['members'][$a->id]['meal_cost']);
        $this->assertSame('90.00', $custom['members'][$b->id]['meal_cost']);
        $this->assertSame('100.00', $custom['unrecovered_food']);
        $this->assertNotEmpty($custom['warnings']);

        $settings->save($this->mess, new SettlementPolicy('custom'));
        $this->assertNotEmpty(app(SettlementService::class)->calculate($this->period)['errors']);
    }
}
