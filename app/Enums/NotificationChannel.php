<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case Push = 'push';
    case Sms = 'sms';
}
