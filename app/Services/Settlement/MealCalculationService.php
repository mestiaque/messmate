<?php

namespace App\Services\Settlement;

use App\Enums\EntryStatus;
use App\Models\AccountingPeriod;
use App\Support\Decimal;

/**
 * Approved weighted units per member (entries use their saved factor snapshots)
 * plus approved meal adjustments, and approved guest units.
 */
class MealCalculationService
{
    public function units(AccountingPeriod $period): array
    {
        $entries = $period->mealEntries()->where('status', EntryStatus::Approved)
            ->groupBy('member_id')->selectRaw('member_id, SUM(total_units) AS units')->pluck('units', 'member_id');
        $adjustments = $period->mealAdjustments()->where('status', EntryStatus::Approved)
            ->groupBy('member_id')->selectRaw('member_id, SUM(adjustment_units) AS units')->pluck('units', 'member_id');
        $guests = $period->guestMeals()->where('status', EntryStatus::Approved)->get(['sponsor_member_id', 'weighted_units']);

        $members = [];
        foreach ($entries->keys()->merge($adjustments->keys())->unique() as $id) {
            $members[$id] = [
                'entries' => Decimal::of($entries[$id] ?? '0'),
                'adjustments' => Decimal::of($adjustments[$id] ?? '0'),
            ];
            $members[$id]['total'] = Decimal::add($members[$id]['entries'], $members[$id]['adjustments']);
        }

        $sponsored = [];
        foreach ($guests->whereNotNull('sponsor_member_id')->groupBy('sponsor_member_id') as $id => $rows) {
            $sponsored[$id] = Decimal::sum($rows->pluck('weighted_units'));
        }

        return [
            'members' => $members,
            'sponsored_guests' => $sponsored,
            'unsponsored_guests' => Decimal::sum($guests->whereNull('sponsor_member_id')->pluck('weighted_units')),
            'member_total' => Decimal::sum(array_column($members, 'total')),
            'guest_total' => Decimal::sum($guests->pluck('weighted_units')),
        ];
    }
}
