<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'complexity',
        'duration',
        'start_date',
        'end_date',
        'project_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function recurringTask(): HasOne
    {
        return $this->hasOne(RecurringTask::class);
    }

    public function pomodoroSessions(): HasMany
    {
        return $this->hasMany(PomodoroSession::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
