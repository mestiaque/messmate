<?php

namespace App\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileWallet = 'mobile_wallet';
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Expense = 'expense';
    case Income = 'income';
    case Equity = 'equity';

    /**
     * Debit-normal accounts grow with debits; the rest grow with credits.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this, [self::Cash, self::Bank, self::MobileWallet, self::Receivable, self::Expense], true);
    }

    public function isMoney(): bool
    {
        return in_array($this, [self::Cash, self::Bank, self::MobileWallet], true);
    }
}
