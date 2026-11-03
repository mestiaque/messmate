<?php

namespace App\Enums;

enum GuestPaymentStatus: string
{
    case NotApplicable = 'not_applicable';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
}
