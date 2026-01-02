<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function dotColor(): string
    {
        return match ($this) {
            self::Low => 'bg-zinc-400',
            self::Medium => 'bg-yellow-400',
            self::High => 'bg-orange-500',
            self::Urgent => 'bg-red-500',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Low => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
            self::Medium => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
            self::High => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
            self::Urgent => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        };
    }
}
