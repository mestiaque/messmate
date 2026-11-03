<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['response_data' => 'array', 'expires_at' => 'datetime'];
    }
}
