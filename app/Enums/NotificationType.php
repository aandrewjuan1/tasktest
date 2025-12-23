<?php

namespace App\Enums;

enum NotificationType: string
{
    case Reminder = 'reminder';
    case TaskDue = 'task_due';
    case EventStart = 'event_start';
    case PomodoroBreak = 'pomodoro_break';
    case PomodoroCycleComplete = 'pomodoro_cycle_complete';
    case Achievement = 'achievement';
    case System = 'system';
}
