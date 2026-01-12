<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInstance extends Model
{
    protected $fillable = [
        'recurring_event_id',
        'event_id',
        'instance_date',
        'status',
        'cancelled',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'instance_date' => 'date',
            'cancelled' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function recurringEvent(): BelongsTo
    {
        return $this->belongsTo(RecurringEvent::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
