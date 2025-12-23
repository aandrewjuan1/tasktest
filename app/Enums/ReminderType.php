<?php

namespace App\Enums;

enum ReminderType: string
{
    case TaskDue = 'task_due';
    case EventStart = 'event_start';
    case Custom = 'custom';
}
