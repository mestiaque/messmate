<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealEntryItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_factor_snapshot' => 'decimal:6',
            'weighted_units' => 'decimal:4',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(MealEntry::class, 'meal_entry_id');
    }

    public function mealType(): BelongsTo
    {
        return $this->belongsTo(MealType::class);
    }
}
