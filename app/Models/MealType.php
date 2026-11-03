<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'code', 'unit_factor', 'default_enabled', 'is_active', 'sort_order'])]
class MealType extends Model
{
    protected function casts(): array
    {
        return [
            'unit_factor' => 'decimal:6',
            'default_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(Mess::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
