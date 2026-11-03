<?php

namespace App\Services\Meals;

use App\Enums\EntryStatus;
use App\Models\GuestMeal;
use App\Models\MealAdjustment;
use App\Models\MealEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approve / reject / void for meal entries, guest meals and meal adjustments.
 */
class MealApprovalService
{
    public const TYPES = [
        'meal-entries' => MealEntry::class,
        'guest-meals' => GuestMeal::class,
        'adjustments' => MealAdjustment::class,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(MealEntry|GuestMeal|MealAdjustment $record, User $actor): void
    {
        $this->change($record, EntryStatus::Submitted, EntryStatus::Approved, $actor);
    }

    public function reject(MealEntry|GuestMeal|MealAdjustment $record, User $actor, string $reason): void
    {
        $this->change($record, EntryStatus::Submitted, EntryStatus::Rejected, $actor, $reason);
    }

    public function void(MealEntry|GuestMeal|MealAdjustment $record, User $actor, string $reason): void
    {
        if (! $record->period->status->acceptsEntries()) {
            throw ValidationException::withMessages(['status' => "The period \"{$record->period->name}\" is not open."]);
        }

        $this->change($record, null, EntryStatus::Voided, $actor, $reason);
    }

    private function change(Model $record, ?EntryStatus $from, EntryStatus $to, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($record, $from, $to, $actor, $reason) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $current = $record->status;

            if (! $record->period->status->acceptsApprovals()) {
                throw ValidationException::withMessages(['status' => "The period \"{$record->period->name}\" is {$record->period->status->value}."]);
            }

            if ($current === EntryStatus::Voided || ($from && $current !== $from)) {
                throw ValidationException::withMessages(['status' => "This record is {$current->value} and cannot be {$to->value}."]);
            }

            $record->status = $to;

            if ($to === EntryStatus::Approved) {
                $record->approved_by = $actor->id;
                $record->approved_at = now();
            }

            if ($record instanceof MealEntry && $to === EntryStatus::Rejected) {
                $record->rejection_reason = $reason;
            }

            $record->save();

            $this->audit->log(
                class_basename($record).'.'.$to->value,
                $record,
                ['status' => $current->value],
                array_filter(['status' => $to->value, 'reason' => $reason]),
            );
        });
    }
}
