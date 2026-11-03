<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\GuestPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestMeal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_factor_snapshot' => 'decimal:6',
            'weighted_units' => 'decimal:4',
            'charge_amount' => 'decimal:2',
            'payment_status' => GuestPaymentStatus::class,
            'status' => EntryStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'sponsor_member_id');
    }

    public function mealType(): BelongsTo
    {
        return $this->belongsTo(MealType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
