<?php

namespace App\Enums;

/**
 * Approval lifecycle shared by meal entries, guest meals and meal adjustments.
 */
enum EntryStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Voided => 'dark',
        };
    }
}
