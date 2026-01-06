<?php

namespace App\Enums;

enum EventStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Tentative = 'tentative';
    case Ongoing = 'ongoing';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
            self::Cancelled => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
            self::Completed => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
            self::Tentative => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
            self::Ongoing => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
        };
    }
}
