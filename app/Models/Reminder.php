<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    protected $fillable = [
        'user_id',
        'remindable_id',
        'remindable_type',
        'reminder_type',
        'trigger_time',
        'time_before_unit',
        'time_before_value',
        'is_recurring',
        'is_sent',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_time' => 'datetime',
            'is_recurring' => 'boolean',
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'time_before_value' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
