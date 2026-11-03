<?php

namespace App\Services\Settlement\Strategies;

use App\Support\Decimal;

/**
 * Default: rate = food pool / all eligible weighted units (members, adjustments
 * and guests). The pool is distributed by largest remainder so the shares add
 * up to the pool exactly.
 */
class WeightedMealRateStrategy implements MealRateStrategyInterface
{
    private array $breakdown = [];

    public function key(): string
    {
        return 'weighted';
    }

    public function validate(MealRateInput $input): array
    {
        $errors = [];

        foreach ($input->memberUnits as $id => $units) {
            if (Decimal::compare($units, '0') < 0) {
                $errors[] = "Member #{$id} has negative meal units after adjustments.";
            }
        }

        if (Decimal::compare($input->pool, '0') > 0 && Decimal::isZero($this->totalUnits($input))) {
            $errors[] = 'There is food cost but no meals were recorded, so a meal rate cannot be calculated.';
        }

        return $errors;
    }

    public function calculate(MealRateInput $input): MealRateResult
    {
        $total = $this->totalUnits($input);

        $weights = [];
        foreach ($input->memberUnits as $id => $units) {
            $weights["m{$id}"] = $units;
        }
        foreach ($input->sponsoredGuestUnits as $id => $units) {
            $weights["g{$id}"] = $units;
        }
        $weights['u'] = $input->unsponsoredGuestUnits;

        $shares = Decimal::isZero($total) ? array_map(fn () => '0.00', $weights) : Decimal::allocate($input->pool, $weights);
        $rate = Decimal::isZero($total) ? '0.000000' : bcdiv($input->pool, $total, 6);

        $memberCost = [];
        foreach ($input->memberUnits as $id => $units) {
            $memberCost[$id] = $shares["m{$id}"];
        }
        $guestCost = [];
        foreach ($input->sponsoredGuestUnits as $id => $units) {
            $guestCost[$id] = $shares["g{$id}"];
        }

        $this->breakdown = [
            'formula' => 'meal rate = food pool / total weighted units',
            'pool' => $input->pool,
            'total_units' => $total,
            'rate' => $rate,
            'rounding' => 'pool distributed by largest remainder; shares sum exactly to the pool',
        ];

        return new MealRateResult($rate, $total, $memberCost, $guestCost, $shares['u'], '0.00');
    }

    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    private function totalUnits(MealRateInput $input): string
    {
        return Decimal::sum([...array_values($input->memberUnits), ...array_values($input->sponsoredGuestUnits), $input->unsponsoredGuestUnits]);
    }
}
