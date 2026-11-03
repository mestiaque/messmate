<?php

namespace App\Services\Settlement\Strategies;

use App\Support\Decimal;

/**
 * Food pool shared equally by eligible members regardless of meals eaten.
 * Guest meals are not charged separately under this strategy.
 */
class EqualMealRateStrategy implements MealRateStrategyInterface
{
    private array $breakdown = [];

    public function key(): string
    {
        return 'equal';
    }

    public function validate(MealRateInput $input): array
    {
        return Decimal::compare($input->pool, '0') > 0 && ! $input->eligibleMemberIds
            ? ['There is food cost but no eligible members to share it.']
            : [];
    }

    public function calculate(MealRateInput $input): MealRateResult
    {
        $ids = $input->eligibleMemberIds;
        $shares = $ids ? Decimal::allocate($input->pool, array_fill_keys($ids, '1')) : [];

        $memberCost = [];
        foreach (array_unique([...array_keys($input->memberUnits), ...$ids]) as $id) {
            $memberCost[$id] = $shares[$id] ?? '0.00';
        }

        $count = count($ids);
        $perMember = $count ? bcdiv($input->pool, (string) $count, 6) : '0.000000';
        $total = Decimal::sum([...array_values($input->memberUnits), ...array_values($input->sponsoredGuestUnits), $input->unsponsoredGuestUnits]);

        $this->breakdown = [
            'formula' => 'each eligible member pays food pool / eligible members',
            'pool' => $input->pool,
            'eligible_members' => $count,
            'per_member' => $perMember,
            'note' => 'guest meals are not charged under the equal strategy',
        ];

        return new MealRateResult($perMember, $total, $memberCost, array_map(fn () => '0.00', $input->sponsoredGuestUnits), '0.00', '0.00');
    }

    public function getBreakdown(): array
    {
        return $this->breakdown;
    }
}
