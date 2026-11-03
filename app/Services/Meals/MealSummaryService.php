<?php

namespace App\Services\Meals;

use App\Enums\EntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Mess;
use App\Models\MessMember;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-side aggregates for meal screens. These are operational totals, not the
 * settlement calculation (Phase 4).
 */
class MealSummaryService
{
    private const COUNTED = [EntryStatus::Submitted, EntryStatus::Approved];

    /**
     * Per meal-type quantities and weighted units for one date.
     */
    public function daily(Mess $mess, string $date): array
    {
        $byType = DB::table('meal_entry_items')
            ->join('meal_entries', 'meal_entries.id', '=', 'meal_entry_items.meal_entry_id')
            ->where('meal_entries.mess_id', $mess->id)
            ->whereDate('meal_entries.meal_date', $date)
            ->whereIn('meal_entries.status', self::COUNTED)
            ->groupBy('meal_entry_items.meal_type_id')
            ->selectRaw('meal_entry_items.meal_type_id, SUM(quantity) AS quantity, SUM(weighted_units) AS units')
            ->get()
            ->keyBy('meal_type_id');

        $entries = $mess->mealEntries()->whereDate('meal_date', $date)->whereIn('status', self::COUNTED);
        $guests = $mess->guestMeals()->whereDate('meal_date', $date)->whereIn('status', self::COUNTED);

        return [
            'by_type' => $byType,
            'member_units' => Decimal::of((clone $entries)->sum('total_units')),
            'daily_total_units' => Decimal::of((clone $entries)->where('tracking_mode', 'daily_total')->sum('total_units')),
            'guest_units' => Decimal::of($guests->sum('weighted_units')),
            'pending' => (clone $entries)->where('status', EntryStatus::Submitted)->count(),
        ];
    }

    /**
     * Units per day across a date range, optionally for one member.
     *
     * @return array<string, array{units: string, pending: int}>
     */
    public function calendar(Mess $mess, Carbon $from, Carbon $to, ?MessMember $member = null): array
    {
        return $mess->mealEntries()
            ->whereBetween('meal_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', self::COUNTED)
            ->when($member, fn ($q) => $q->where('member_id', $member->id))
            ->groupBy('meal_date')
            ->selectRaw("meal_date, SUM(total_units) AS units, SUM(status = 'submitted') AS pending")
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->meal_date)->toDateString() => ['units' => Decimal::of($row->units), 'pending' => (int) $row->pending]])
            ->all();
    }

    /**
     * Totals for one member within a period.
     */
    public function memberPeriod(MessMember $member, AccountingPeriod $period): array
    {
        $entries = $period->mealEntries()->where('member_id', $member->id);
        $adjustments = $period->mealAdjustments()->where('member_id', $member->id);

        return [
            'approved_units' => Decimal::of((clone $entries)->where('status', EntryStatus::Approved)->sum('total_units')),
            'pending_units' => Decimal::of((clone $entries)->where('status', EntryStatus::Submitted)->sum('total_units')),
            'adjustment_units' => Decimal::of((clone $adjustments)->where('status', EntryStatus::Approved)->sum('adjustment_units')),
            'sponsored_guest_units' => Decimal::of($period->guestMeals()->where('sponsor_member_id', $member->id)->where('status', EntryStatus::Approved)->sum('weighted_units')),
            'days' => (clone $entries)->whereIn('status', self::COUNTED)->count(),
        ];
    }
}
