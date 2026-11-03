<?php

namespace App\Enums;

enum RoleScope: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
    case Mess = 'mess';
}
