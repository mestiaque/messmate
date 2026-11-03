<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case Food = 'food';
    case Utility = 'utility';
    case Rent = 'rent';
    case Maintenance = 'maintenance';
    case Salary = 'salary';
    case Other = 'other';
}
