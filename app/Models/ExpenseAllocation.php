<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allocated_amount' => 'decimal:2', 'allocation_basis' => 'decimal:4'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(MessMember::class, 'member_id');
    }
}
