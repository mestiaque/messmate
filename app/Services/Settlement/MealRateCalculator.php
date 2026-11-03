<?php

namespace App\Services\Settlement;

use App\Services\Settlement\Strategies\ConfigurableCustomStrategy;
use App\Services\Settlement\Strategies\EqualMealRateStrategy;
use App\Services\Settlement\Strategies\MealRateStrategyInterface;
use App\Services\Settlement\Strategies\WeightedMealRateStrategy;

class MealRateCalculator
{
    public function strategy(SettlementPolicy $policy): MealRateStrategyInterface
    {
        return match ($policy->strategy) {
            'equal' => new EqualMealRateStrategy,
            'custom' => new ConfigurableCustomStrategy,
            default => new WeightedMealRateStrategy,
        };
    }
}
