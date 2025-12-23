<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringEvent extends Model
{
    protected $fillable = [
        'event_id',
        'recurrence_type',
        'interval',
        'days_of_week',
        'day_of_month',
        'nth_weekday',
        'rrule',
        'start_date',
        'end_date',
        'occurrence_count',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_type' => RecurrenceType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'interval' => 'integer',
            'occurrence_count' => 'integer',
            'day_of_month' => 'integer',
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
}
