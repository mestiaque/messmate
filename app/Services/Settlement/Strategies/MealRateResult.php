<?php

namespace App\Services\Settlement\Strategies;

final class MealRateResult
{
    /**
     * @param  array<int, string>  $memberCost  member_id => cost of own meals
     * @param  array<int, string>  $guestCost  sponsor member_id => cost of sponsored guests
     */
    public function __construct(
        public readonly string $rate,
        public readonly string $totalUnits,
        public readonly array $memberCost,
        public readonly array $guestCost,
        public readonly string $unsponsoredGuestCost,
        public readonly string $unrecovered,
    ) {}
}
