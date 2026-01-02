<?php

namespace App\Enums;

enum TaskStatus: string
{
    case ToDo = 'to_do';
    case Doing = 'doing';
    case Done = 'done';

    public function badgeColor(): string
    {
        return match ($this) {
            self::ToDo => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
            self::Doing => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
            self::Done => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        };
    }
}
