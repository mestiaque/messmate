<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    public const CASH = 'cash';
    public const BANK = 'bank';
    public const MOBILE_WALLET = 'mobile_wallet';
    public const MEMBER_CREDIT = 'member_credit';
    public const PAYABLE = 'payable';
    public const FOOD_EXPENSE = 'food_expense';
    public const OTHER_EXPENSE = 'other_expense';
    public const EXPENSE_RECOVERY = 'expense_recovery';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'account_id');
    }
}
