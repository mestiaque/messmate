<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\MealTrackingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
            'tracking_mode' => MealTrackingMode::class,
            'status' => EntryStatus::class,
            'total_units' => 'decimal:4',
            'raw_count' => 'decimal:4',
            'conversion_factor' => 'decimal:6',
            'approved_at' => 'datetime',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'member_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealEntryItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
