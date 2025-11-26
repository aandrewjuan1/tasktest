<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomodoroSession extends Model
{
    protected $fillable = [
        'user_id',
        'task_id',
        'session_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'work_cycles',
        'break_cycles',
        'is_completed',
        'interruptions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'work_cycles' => 'integer',
            'break_cycles' => 'integer',
            'is_completed' => 'boolean',
            'interruptions' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
