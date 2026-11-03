<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BazarCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_food_related' => 'boolean', 'is_active' => 'boolean'];
    }
}
