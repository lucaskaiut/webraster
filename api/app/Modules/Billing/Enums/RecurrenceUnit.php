<?php

namespace App\Modules\Billing\Enums;

enum RecurrenceUnit: string
{
    case DAYS = 'days';
    case WEEKS = 'weeks';
    case MONTHS = 'months';
    case YEARS = 'years';
}
