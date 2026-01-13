<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RecurringTask extends Model
{
    protected $fillable = [
        'task_id',
        'recurrence_type',
        'interval',
        'start_datetime',
        'end_datetime',
        'days_of_week',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_type' => RecurrenceType::class,
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'interval' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function taskInstances(): HasMany
    {
        return $this->hasMany(TaskInstance::class);
    }

    public function taskExceptions(): HasMany
    {
        return $this->hasMany(TaskException::class);
    }

    /**
     * Calculate occurrence dates for a given date range.
     */
    public function calculateOccurrences(Carbon $startDate, Carbon $endDate): Collection
    {
        $occurrences = collect();

        // Use task's start_datetime if recurrence start_datetime is not set
        $recurrenceStart = $this->start_datetime ?? $this->task?->start_datetime ?? now();
        $currentDate = Carbon::parse($recurrenceStart)->startOfDay();
        $endLimit = $this->end_datetime ? Carbon::parse($this->end_datetime)->endOfDay() : null;

        // Don't start before the requested start date
        if ($currentDate->lt($startDate)) {
            $currentDate = $startDate->copy();
        }

        // Don't go beyond the requested end date or recurrence end date
        $maxDate = $endLimit && $endLimit->lt($endDate) ? $endLimit : $endDate;

        while ($currentDate->lte($maxDate)) {
            if ($this->shouldGenerateOnDate($currentDate)) {
                $occurrences->push($currentDate->copy());
            }

            $currentDate = $this->getNextOccurrenceDate($currentDate);
        }

        return $occurrences;
    }

    /**
     * Get the next occurrence date from a given date.
     */
    public function getNextOccurrenceDate(Carbon $fromDate): Carbon
    {
        $nextDate = $fromDate->copy();

        return match ($this->recurrence_type) {
            RecurrenceType::Daily => $nextDate->addDays($this->interval),
            RecurrenceType::Weekly => $this->getNextWeeklyDate($nextDate),
            RecurrenceType::Monthly => $nextDate->addMonths($this->interval),
            RecurrenceType::Yearly => $nextDate->addYears($this->interval),
        };
    }

    /**
     * Check if an occurrence should be generated on a specific date.
     */
    public function shouldGenerateOnDate(Carbon $date): bool
    {
        // Use task's start_datetime if recurrence start_datetime is not set
        $recurrenceStart = $this->start_datetime ?? $this->task?->start_datetime ?? now();

        // Compare dates at start of day to avoid time-based comparison issues
        $recurrenceStartDate = Carbon::parse($recurrenceStart)->startOfDay();
        $dateStartOfDay = $date->copy()->startOfDay();

        // Check if date is before start_datetime
        if ($dateStartOfDay->lt($recurrenceStartDate)) {
            return false;
        }

        // Check if date is after end_datetime (compare at end of day for end_datetime)
        if ($this->end_datetime) {
            $endDate = Carbon::parse($this->end_datetime)->endOfDay();
            if ($dateStartOfDay->gt($endDate)) {
                return false;
            }
        }

        // For weekly recurrence, check if date matches days_of_week
        if ($this->recurrence_type === RecurrenceType::Weekly && $this->days_of_week) {
            $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 6 = Saturday
            $allowedDays = array_map('intval', explode(',', $this->days_of_week));

            return in_array($dayOfWeek, $allowedDays);
        }

        return true;
    }

    /**
     * Get next weekly occurrence date considering days_of_week.
     */
    protected function getNextWeeklyDate(Carbon $fromDate): Carbon
    {
        if (! $this->days_of_week) {
            return $fromDate->addWeeks($this->interval);
        }

        $allowedDays = array_map('intval', explode(',', $this->days_of_week));
        $currentDayOfWeek = $fromDate->dayOfWeek;
        $nextDate = $fromDate->copy();

        // Find next allowed day in current week
        $nextDayInWeek = null;
        foreach ($allowedDays as $day) {
            if ($day > $currentDayOfWeek) {
                $nextDayInWeek = $day;
                break;
            }
        }

        if ($nextDayInWeek !== null) {
            $daysToAdd = $nextDayInWeek - $currentDayOfWeek;
            $nextDate->addDays($daysToAdd);
        } else {
            // No more days in current week, go to first allowed day of next interval period
            $firstAllowedDay = min($allowedDays);
            $daysToAdd = (7 * $this->interval) - $currentDayOfWeek + $firstAllowedDay;
            $nextDate->addDays($daysToAdd);
        }

        return $nextDate;
    }

    /**
     * Get exception dates that should be skipped.
     */
    public function getExceptionDates(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->taskExceptions()
            ->whereBetween('exception_date', [$startDate, $endDate])
            ->where('is_deleted', true)
            ->pluck('exception_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));
    }
}
