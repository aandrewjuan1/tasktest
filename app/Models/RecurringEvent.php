<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RecurringEvent extends Model
{
    protected $fillable = [
        'event_id',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventInstances(): HasMany
    {
        return $this->hasMany(EventInstance::class);
    }

    public function eventExceptions(): HasMany
    {
        return $this->hasMany(EventException::class);
    }

    /**
     * Calculate occurrence datetimes for a given date range.
     * Returns collection of arrays with 'start' and 'end' datetime keys.
     */
    public function calculateOccurrences(Carbon $startDate, Carbon $endDate): Collection
    {
        $occurrences = collect();
        $baseEvent = $this->event;

        if (! $baseEvent) {
            return $occurrences;
        }

        // Determine base start time and duration
        $baseStart = null;
        $duration = 60; // Default 1 hour if no end_datetime

        if ($baseEvent->start_datetime && $baseEvent->end_datetime) {
            $baseStart = Carbon::parse($baseEvent->start_datetime);
            $baseEnd = Carbon::parse($baseEvent->end_datetime);
            $duration = $baseStart->diffInMinutes($baseEnd);
        } elseif ($baseEvent->start_datetime) {
            $baseStart = Carbon::parse($baseEvent->start_datetime);
        } elseif ($this->start_datetime) {
            $baseStart = Carbon::parse($this->start_datetime);
        } else {
            $baseStart = now();
        }

        // Use event's start_datetime if recurrence start_datetime is not set
        $recurrenceStart = $this->start_datetime ?? $baseEvent->start_datetime ?? now();
        $recurrenceStartDate = Carbon::parse($recurrenceStart)->startOfDay();
        $endLimit = $this->end_datetime ? Carbon::parse($this->end_datetime)->endOfDay() : null;

        // For yearly recurrence, find the first occurrence within the date range
        if ($this->recurrence_type === RecurrenceType::Yearly) {
            $currentDate = $this->getFirstYearlyOccurrenceInRange($recurrenceStartDate, $startDate, $endDate);
            if (! $currentDate) {
                return $occurrences; // No occurrences in this range
            }
        } else {
            $currentDate = $recurrenceStartDate->copy();
            // Don't start before the requested start date
            if ($currentDate->lt($startDate)) {
                $currentDate = $startDate->copy();
            }
        }

        // Don't go beyond the requested end date or recurrence end date
        $maxDate = $endLimit && $endLimit->lt($endDate) ? $endLimit : $endDate;

        while ($currentDate->lte($maxDate)) {
            if ($this->shouldGenerateOnDate($currentDate)) {
                // Calculate the actual datetime for this occurrence
                $occurrenceStart = $currentDate->copy()
                    ->setTime($baseStart->hour, $baseStart->minute, $baseStart->second);
                $occurrenceEnd = $occurrenceStart->copy()->addMinutes($duration);

                // Only include if it falls within the requested range
                if ($occurrenceStart->lte($endDate) && $occurrenceEnd->gte($startDate)) {
                    $occurrences->push([
                        'start' => $occurrenceStart,
                        'end' => $occurrenceEnd,
                    ]);
                }
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
            RecurrenceType::Monthly => $this->getNextMonthlyDate($nextDate),
            RecurrenceType::Yearly => $this->getNextYearlyDate($nextDate),
            RecurrenceType::Custom => $this->getNextCustomDate($nextDate),
        };
    }

    /**
     * Check if an occurrence should be generated on a specific date.
     */
    public function shouldGenerateOnDate(Carbon $date): bool
    {
        // Use event's start_datetime if recurrence start_datetime is not set
        $recurrenceStart = $this->start_datetime ?? $this->event?->start_datetime ?? now();

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

        // For yearly recurrence, check if month and day match
        if ($this->recurrence_type === RecurrenceType::Yearly) {
            return $date->month === $recurrenceStartDate->month
                && $date->day === $recurrenceStartDate->day;
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
     * Get next monthly occurrence date.
     */
    protected function getNextMonthlyDate(Carbon $fromDate): Carbon
    {
        return $fromDate->addMonths($this->interval);
    }

    /**
     * Get next yearly occurrence date.
     * Ensures we always jump to the correct month/day combination.
     */
    protected function getNextYearlyDate(Carbon $fromDate): Carbon
    {
        $recurrenceStart = $this->start_datetime ?? $this->event?->start_datetime ?? now();
        $recurrenceStartDate = Carbon::parse($recurrenceStart);
        $recurrenceMonth = $recurrenceStartDate->month;
        $recurrenceDay = $recurrenceStartDate->day;

        // Get the next year's occurrence of the same month/day
        $nextYear = $fromDate->year + $this->interval;
        $nextDate = Carbon::create($nextYear, $recurrenceMonth, $recurrenceDay)->startOfDay();

        return $nextDate;
    }

    /**
     * Get next custom occurrence date (placeholder for future custom logic).
     */
    protected function getNextCustomDate(Carbon $fromDate): Carbon
    {
        // For now, treat custom as daily
        // This can be extended based on rrule or other custom patterns
        return $fromDate->addDay();
    }

    /**
     * Get the first yearly occurrence within a date range.
     * For yearly events, we need to find the next occurrence of the month/day
     * that falls within the requested date range.
     */
    protected function getFirstYearlyOccurrenceInRange(Carbon $recurrenceStart, Carbon $startDate, Carbon $endDate): ?Carbon
    {
        $recurrenceMonth = $recurrenceStart->month;
        $recurrenceDay = $recurrenceStart->day;

        // Start from the requested start date
        $currentYear = $startDate->year;
        $currentDate = Carbon::create($currentYear, $recurrenceMonth, $recurrenceDay)->startOfDay();

        // If this year's occurrence is before the start date, try next year
        if ($currentDate->lt($startDate)) {
            $currentDate = Carbon::create($currentYear + 1, $recurrenceMonth, $recurrenceDay)->startOfDay();
        }

        // Check if this occurrence is within the end date
        if ($currentDate->lte($endDate)) {
            return $currentDate;
        }

        return null;
    }

    /**
     * Get exception dates that should be skipped.
     */
    public function getExceptionDates(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->eventExceptions()
            ->whereBetween('exception_date', [$startDate, $endDate])
            ->where('is_deleted', true)
            ->pluck('exception_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));
    }
}
