<?php

namespace App\Models;

use App\Enums\ExpenseCategoryType;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['category_type' => ExpenseCategoryType::class, 'is_active' => 'boolean'];
    }
}
