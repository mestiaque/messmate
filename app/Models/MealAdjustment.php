<?php

namespace App\Models;

use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealAdjustment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'adjustment_units' => 'decimal:4',
            'status' => EntryStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
