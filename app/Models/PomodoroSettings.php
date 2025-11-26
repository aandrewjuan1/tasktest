<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomodoroSettings extends Model
{
    protected $fillable = [
        'user_id',
        'work_duration_minutes',
        'break_duration_minutes',
        'long_break_duration_minutes',
        'cycles_before_long_break',
        'sound_enabled',
        'notifications_enabled',
        'auto_start_next_session',
        'auto_start_break',
    ];

    protected function casts(): array
    {
        return [
            'work_duration_minutes' => 'integer',
            'break_duration_minutes' => 'integer',
            'long_break_duration_minutes' => 'integer',
            'cycles_before_long_break' => 'integer',
            'sound_enabled' => 'boolean',
            'notifications_enabled' => 'boolean',
            'auto_start_next_session' => 'boolean',
            'auto_start_break' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
