<?php

namespace App\Enums;

enum NotificationFrequency: string
{
    case Immediate = 'immediate';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
