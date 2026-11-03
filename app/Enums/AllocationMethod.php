<?php

namespace App\Enums;

enum AllocationMethod: string
{
    case Equal = 'equal';
    case MealBased = 'meal_based';
    case Custom = 'custom';
    case UsageBased = 'usage_based';
    case Excluded = 'excluded';

    /**
     * Methods whose member split is entered when the expense is recorded.
     * Equal and meal-based splits depend on period data and are computed at settlement.
     */
    public function needsAllocations(): bool
    {
        return in_array($this, [self::Custom, self::UsageBased], true);
    }
}
