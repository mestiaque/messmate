<?php

namespace App\Enums;

enum MealTrackingMode: string
{
    case Individual = 'individual';
    case DailyTotal = 'daily_total';
}
