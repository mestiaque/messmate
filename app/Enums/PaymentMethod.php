<?php

namespace App\Enums;

/**
 * How a purchase or expense was paid. Decides the credit side of its ledger entry.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileWallet = 'mobile_wallet';
    case MemberPaid = 'member_paid';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Mess cash',
            self::Bank => 'Mess bank',
            self::MobileWallet => 'Mobile wallet',
            self::MemberPaid => 'Paid by member (mess owes them)',
            self::Credit => 'On credit (vendor payable)',
        };
    }

    /**
     * Methods valid for deposits (money actually received by the mess).
     *
     * @return list<self>
     */
    public static function forDeposits(): array
    {
        return [self::Cash, self::Bank, self::MobileWallet];
    }
}
