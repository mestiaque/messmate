<?php

namespace App\Services\Settlement\Strategies;

interface MealRateStrategyInterface
{
    public function key(): string;

    /**
     * @return list<string> blocking problems; empty when the input can be calculated
     */
    public function validate(MealRateInput $input): array;

    public function calculate(MealRateInput $input): MealRateResult;

    /**
     * Human-readable explanation of the last calculation, saved in the snapshot.
     */
    public function getBreakdown(): array;
}
