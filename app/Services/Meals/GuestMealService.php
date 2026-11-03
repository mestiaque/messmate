<?php

namespace App\Services\Meals;

use App\Enums\EntryStatus;
use App\Enums\GuestPaymentStatus;
use App\Models\GuestMeal;
use App\Models\Mess;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Periods\AccountingPeriodService;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

class GuestMealService
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{guest_name: string, meal_date: string, quantity: string, meal_type_id?: ?int, sponsor_member_id?: ?int, charge_amount?: ?string, notes?: ?string}  $data
     */
    public function create(Mess $mess, array $data, User $actor, bool $approve = false): GuestMeal
    {
        $period = $this->periods->openPeriodFor($mess, $data['meal_date']);

        $factor = '1.000000';
        if (! empty($data['meal_type_id'])) {
            $type = $mess->mealTypes()->where('is_active', true)->find($data['meal_type_id'])
                ?? throw ValidationException::withMessages(['meal_type_id' => 'Unknown or inactive meal type.']);
            $factor = Decimal::of($type->unit_factor, 6);
        }

        if (! empty($data['sponsor_member_id']) && ! $mess->members()->whereKey($data['sponsor_member_id'])->exists()) {
            throw ValidationException::withMessages(['sponsor_member_id' => 'Sponsor must be a member of this mess.']);
        }

        $quantity = Decimal::of($data['quantity']);
        $charge = isset($data['charge_amount']) && $data['charge_amount'] !== '' ? Decimal::of($data['charge_amount'], 2) : null;

        $guest = GuestMeal::create([
            'tenant_id' => $mess->tenant_id,
            'mess_id' => $mess->id,
            'period_id' => $period->id,
            'guest_name' => $data['guest_name'],
            'sponsor_member_id' => $data['sponsor_member_id'] ?? null,
            'meal_type_id' => $data['meal_type_id'] ?? null,
            'meal_date' => $data['meal_date'],
            'quantity' => $quantity,
            'unit_factor_snapshot' => $factor,
            'weighted_units' => Decimal::mul($quantity, $factor),
            'charge_amount' => $charge,
            'payment_status' => $charge === null ? GuestPaymentStatus::NotApplicable : GuestPaymentStatus::Unpaid,
            'status' => $approve ? EntryStatus::Approved : EntryStatus::Submitted,
            'approved_by' => $approve ? $actor->id : null,
            'approved_at' => $approve ? now() : null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log('guest_meal.created', $guest, new: $guest->only(['guest_name', 'meal_date', 'quantity', 'weighted_units', 'charge_amount']));

        return $guest;
    }
}
