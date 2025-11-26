<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInstance extends Model
{
    protected $fillable = [
        'recurring_event_id',
        'event_id',
        'instance_start',
        'instance_end',
        'status',
        'overridden_title',
        'overridden_description',
        'overridden_location',
        'all_day',
        'timezone',
        'cancelled',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'instance_start' => 'datetime',
            'instance_end' => 'datetime',
            'all_day' => 'boolean',
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
