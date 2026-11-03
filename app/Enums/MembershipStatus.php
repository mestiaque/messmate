<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';
    case Left = 'left';
    case Suspended = 'suspended';
}
