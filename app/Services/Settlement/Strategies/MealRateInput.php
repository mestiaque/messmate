<?php

namespace App\Services\Settlement\Strategies;

/**
 * Everything a meal-rate strategy needs. Units are weighted units (4 dp strings).
 */
final class MealRateInput
{
    /**
     * @param  array<int, string>  $memberUnits  member_id => own units (entries + adjustments)
     * @param  array<int, string>  $sponsoredGuestUnits  sponsor member_id => guest units
     * @param  list<int>  $eligibleMemberIds  members sharing equal splits
     */
    public function __construct(
        public readonly string $pool,
        public readonly array $memberUnits,
        public readonly array $sponsoredGuestUnits,
        public readonly string $unsponsoredGuestUnits,
        public readonly array $eligibleMemberIds,
        public readonly ?string $customRate = null,
    ) {}
}
