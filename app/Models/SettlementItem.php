<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_rate' => 'decimal:6', 'amount' => 'decimal:2', 'calculation_reference' => 'array'];
    }
}
