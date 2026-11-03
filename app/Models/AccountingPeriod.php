<?php

namespace App\Models;

use App\Enums\PeriodMode;
use App\Enums\PeriodStatus;
use Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends Model
{
    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodMode::class,
            'status' => PeriodStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'finalized_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class, 'period_id');
    }

    public function guestMeals(): HasMany
    {
        return $this->hasMany(GuestMeal::class, 'period_id');
    }

    public function mealAdjustments(): HasMany
    {
        return $this->hasMany(MealAdjustment::class, 'period_id');
    }

    public function bazarEntries(): HasMany
    {
        return $this->hasMany(BazarEntry::class, 'period_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'period_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class, 'period_id');
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class, 'period_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MonthlySnapshot::class, 'period_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'period_id');
    }

    public function covers(\DateTimeInterface|string $date): bool
    {
        $date = \Illuminate\Support\Carbon::parse($date)->toDateString();

        return $date >= $this->start_date->toDateString() && $date <= $this->end_date->toDateString();
    }
}
