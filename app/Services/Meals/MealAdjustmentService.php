<?php

namespace App\Services\Meals;

use App\Enums\EntryStatus;
use App\Models\AccountingPeriod;
use App\Models\MealAdjustment;
use App\Models\MessMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

class MealAdjustmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(AccountingPeriod $period, MessMember $member, string $units, string $reason, User $actor, bool $approve = false): MealAdjustment
    {
        if (! $period->status->acceptsEntries()) {
            throw ValidationException::withMessages(['period_id' => "The period \"{$period->name}\" is not open."]);
        }

        if ($member->mess_id !== $period->mess_id) {
            throw ValidationException::withMessages(['member_id' => 'Member does not belong to this mess.']);
        }

        $units = Decimal::of($units);

        if (Decimal::isZero($units)) {
            throw ValidationException::withMessages(['adjustment_units' => 'An adjustment cannot be zero.']);
        }

        $adjustment = MealAdjustment::create([
            'tenant_id' => $period->tenant_id,
            'mess_id' => $period->mess_id,
            'period_id' => $period->id,
            'member_id' => $member->id,
            'adjustment_units' => $units,
            'reason' => $reason,
            'status' => $approve ? EntryStatus::Approved : EntryStatus::Submitted,
            'approved_by' => $approve ? $actor->id : null,
            'approved_at' => $approve ? now() : null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log('meal_adjustment.created', $adjustment, new: ['member_id' => $member->id, 'units' => $units, 'reason' => $reason]);

        return $adjustment;
    }
}
