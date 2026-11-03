<?php

namespace App\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reversed = 'reversed';

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Reversed => 'dark',
        };
    }
}
