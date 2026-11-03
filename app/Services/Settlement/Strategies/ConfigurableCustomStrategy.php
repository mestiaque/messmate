<?php

namespace App\Services\Settlement\Strategies;

use App\Support\Decimal;

/**
 * A fixed, administrator-configured rate per weighted unit. Any difference
 * between the pool and what the rate recovers is reported explicitly as
 * unrecovered (positive) or surplus (negative); it is never hidden in shares.
 */
class ConfigurableCustomStrategy implements MealRateStrategyInterface
{
    private array $breakdown = [];

    public function key(): string
    {
        return 'custom';
    }

    public function validate(MealRateInput $input): array
    {
        if ($input->customRate === null || ! is_numeric($input->customRate) || Decimal::compare($input->customRate, '0') < 0) {
            return ['The custom strategy needs a non-negative fixed rate in the settlement settings.'];
        }

        return [];
    }

    public function calculate(MealRateInput $input): MealRateResult
    {
        $rate = Decimal::of($input->customRate, 6);

        $memberCost = array_map(fn ($u) => Decimal::mul($u, $rate, 2), $input->memberUnits);
        $guestCost = array_map(fn ($u) => Decimal::mul($u, $rate, 2), $input->sponsoredGuestUnits);
        $unsponsored = Decimal::mul($input->unsponsoredGuestUnits, $rate, 2);
        $recovered = Decimal::sum([...array_values($memberCost), ...array_values($guestCost), $unsponsored], 2);
        $total = Decimal::sum([...array_values($input->memberUnits), ...array_values($input->sponsoredGuestUnits), $input->unsponsoredGuestUnits]);

        $this->breakdown = [
            'formula' => 'cost = units × fixed rate (rounded per member)',
            'rate' => $rate,
            'pool' => $input->pool,
            'recovered' => $recovered,
            'unrecovered' => bcsub($input->pool, $recovered, 2),
        ];

        return new MealRateResult($rate, $total, $memberCost, $guestCost, $unsponsored, bcsub($input->pool, $recovered, 2));
    }

    public function getBreakdown(): array
    {
        return $this->breakdown;
    }
}
