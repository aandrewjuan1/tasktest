<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'all_day',
        'timezone',
        'location',
        'color',
        'status',
        'recurring_event_id',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function recurringEvent(): HasOne
    {
        return $this->hasOne(RecurringEvent::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
