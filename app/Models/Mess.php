<?php

namespace App\Models;

use App\Enums\MealTrackingMode;
use App\Enums\MessStatus;
use App\Enums\PeriodMode;
use Database\Factories\MessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'description', 'address', 'contact_phone', 'contact_email',
    'currency', 'timezone', 'default_period_mode', 'default_meal_tracking_mode', 'status',
])]
class Mess extends Model
{
    /** @use HasFactory<MessFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => MessStatus::class,
            'default_period_mode' => PeriodMode::class,
            'default_meal_tracking_mode' => MealTrackingMode::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MessMember::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    public function mealTypes(): HasMany
    {
        return $this->hasMany(MealType::class);
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }

    public function guestMeals(): HasMany
    {
        return $this->hasMany(GuestMeal::class);
    }

    public function mealAdjustments(): HasMany
    {
        return $this->hasMany(MealAdjustment::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function bazarCategories(): HasMany
    {
        return $this->hasMany(BazarCategory::class);
    }

    public function bazarEntries(): HasMany
    {
        return $this->hasMany(BazarEntry::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MonthlySnapshot::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MessInvitation::class);
    }
}
