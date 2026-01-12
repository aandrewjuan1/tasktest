<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

trait HandlesDateRanges
{
    protected function getDateRangeForViewTrait(string $viewMode, ?Carbon $currentDate, ?Carbon $weekStartDate): array
    {
        if (in_array($viewMode, ['list', 'kanban'])) {
            // For list/kanban views, use the current date (single day)
            $date = $currentDate ?? now();
            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $date->copy()->endOfDay(),
            ];
        } elseif ($viewMode === 'daily-timegrid') {
            // For daily timegrid, use the current date
            $date = $currentDate ?? now();
            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $date->copy()->endOfDay(),
            ];
        } elseif ($viewMode === 'weekly-timegrid') {
            // For weekly timegrid, use the week range
            $weekStart = $weekStartDate ?? now()->startOfWeek();
            return [
                'start' => $weekStart->copy()->startOfDay(),
                'end' => $weekStart->copy()->endOfWeek()->endOfDay(),
            ];
        }

        // Default: current day
        $date = now();
        return [
            'start' => $date->copy()->startOfDay(),
            'end' => $date->copy()->endOfDay(),
        ];
    }
}
