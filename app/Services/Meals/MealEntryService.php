<?php

namespace App\Services\Meals;

use App\Enums\EntryStatus;
use App\Enums\MealTrackingMode;
use App\Models\MealEntry;
use App\Models\MealType;
use App\Models\Mess;
use App\Models\MessMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one member's meals for one date, in either tracking mode. A member has
 * at most one entry per date (unique period+member+date), and that entry's mode
 * cannot be switched while it is live.
 */
class MealEntryService
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int|string, string|int|float|null>  $quantities  meal_type_id => quantity
     */
    public function saveIndividual(Mess $mess, MessMember $member, string $date, array $quantities, User $actor, bool $approve = false, ?string $notes = null): ?MealEntry
    {
        $types = $mess->mealTypes()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        $items = [];
        foreach ($quantities as $typeId => $quantity) {
            $quantity = Decimal::of($quantity);

            if (Decimal::compare($quantity, '0') < 0) {
                throw ValidationException::withMessages(['quantities' => 'Meal quantities cannot be negative.']);
            }

            /** @var MealType|null $type */
            $type = $types->get((int) $typeId);

            if (! $type) {
                throw ValidationException::withMessages(['quantities' => 'Unknown meal type for this mess.']);
            }

            if (! $type->is_active && ! Decimal::isZero($quantity)) {
                throw ValidationException::withMessages(['quantities' => "{$type->name} is not active."]);
            }

            $factor = Decimal::of($type->unit_factor, 6);
            $items[$type->id] = [
                'quantity' => $quantity,
                'unit_factor_snapshot' => $factor,
                'weighted_units' => Decimal::mul($quantity, $factor),
            ];
        }

        $total = Decimal::sum(array_column($items, 'weighted_units'));

        return $this->persist($mess, $member, $date, MealTrackingMode::Individual, $total, $actor, $approve, $notes, items: $items);
    }

    /**
     * Daily total: either weighted $units directly, or $rawCount × $factor.
     */
    public function saveDailyTotal(Mess $mess, MessMember $member, string $date, ?string $units, User $actor, bool $approve = false, ?string $notes = null, ?string $rawCount = null, ?string $factor = null): ?MealEntry
    {
        $derivation = ['raw_count' => null, 'conversion_factor' => null];

        if ($rawCount !== null && $rawCount !== '') {
            $rawCount = Decimal::of($rawCount);
            $factor = Decimal::of($factor ?? '1', 6);
            $units = Decimal::mul($rawCount, $factor);
            $derivation = ['raw_count' => $rawCount, 'conversion_factor' => $factor];
        }

        $units = Decimal::of($units);

        if (Decimal::compare($units, '0') < 0) {
            throw ValidationException::withMessages(['units' => 'Meal units cannot be negative.']);
        }

        return $this->persist($mess, $member, $date, MealTrackingMode::DailyTotal, $units, $actor, $approve, $notes, derivation: $derivation);
    }

    public function findLive(MessMember $member, string $date): ?MealEntry
    {
        return MealEntry::query()
            ->where('member_id', $member->id)
            ->whereDate('meal_date', $date)
            ->where('status', '!=', EntryStatus::Voided)
            ->first();
    }

    private function persist(Mess $mess, MessMember $member, string $date, MealTrackingMode $mode, string $total, User $actor, bool $approve, ?string $notes, array $items = [], array $derivation = []): ?MealEntry
    {
        $date = Carbon::parse($date)->toDateString();

        if ($member->mess_id !== $mess->id) {
            throw ValidationException::withMessages(['member' => 'Member does not belong to this mess.']);
        }

        if (! $member->isEffectiveOn($date)) {
            throw ValidationException::withMessages(['member' => "{$member->full_name} is not a member of this mess on {$date}."]);
        }

        $period = $this->periods->openPeriodFor($mess, $date);

        return DB::transaction(function () use ($mess, $member, $date, $mode, $total, $actor, $approve, $notes, $items, $derivation, $period) {
            $entry = MealEntry::query()
                ->where('period_id', $period->id)
                ->where('member_id', $member->id)
                ->whereDate('meal_date', $date)
                ->lockForUpdate()
                ->first();

            if ($entry && $entry->status !== EntryStatus::Voided && $entry->tracking_mode !== $mode) {
                $existing = $entry->tracking_mode === MealTrackingMode::Individual ? 'individual meals' : 'a daily total';
                throw ValidationException::withMessages(['tracking_mode' => "{$member->full_name} already has {$existing} recorded for {$date}. Void it before switching mode."]);
            }

            if (! $entry && Decimal::isZero($total)) {
                return null; // Nothing eaten and nothing recorded yet: no row needed.
            }

            $old = $entry ? $this->snapshot($entry) : null;
            $isCorrection = $entry && in_array($entry->status, [EntryStatus::Approved, EntryStatus::Rejected], true);

            $entry ??= new MealEntry([
                'tenant_id' => $mess->tenant_id,
                'mess_id' => $mess->id,
                'period_id' => $period->id,
                'member_id' => $member->id,
                'meal_date' => $date,
                'created_by' => $actor->id,
            ]);

            $entry->fill([
                'tracking_mode' => $mode,
                'total_units' => $total,
                'raw_count' => $derivation['raw_count'] ?? null,
                'conversion_factor' => $derivation['conversion_factor'] ?? null,
                'notes' => $notes ?? $entry->notes,
                'rejection_reason' => null,
                'status' => $approve ? EntryStatus::Approved : EntryStatus::Submitted,
                'approved_by' => $approve ? $actor->id : null,
                'approved_at' => $approve ? now() : null,
            ]);
            $entry->save();

            $entry->items()->delete();
            foreach ($items as $typeId => $item) {
                if (! Decimal::isZero($item['quantity'])) {
                    $entry->items()->create(['meal_type_id' => $typeId] + $item);
                }
            }

            $new = $this->snapshot($entry->load('items'));

            if (! $old) {
                $this->audit->log('meal_entry.created', $entry, new: $new);
            } elseif ($old !== $new || $isCorrection) {
                $this->audit->log($isCorrection ? 'meal_entry.corrected' : 'meal_entry.updated', $entry, $old, $new);
            }

            return $entry;
        });
    }

    private function snapshot(MealEntry $entry): array
    {
        return [
            'mode' => $entry->tracking_mode->value,
            'total_units' => (string) $entry->total_units,
            'status' => $entry->status->value,
            'items' => $entry->items->mapWithKeys(fn ($i) => [$i->meal_type_id => (string) $i->quantity])->all(),
        ];
    }
}
