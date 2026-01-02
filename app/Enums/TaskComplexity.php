<?php

namespace App\Enums;

enum TaskComplexity: string
{
    case Simple = 'simple';
    case Moderate = 'moderate';
    case Complex = 'complex';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Simple => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
            self::Moderate => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
            self::Complex => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        };
    }
}
