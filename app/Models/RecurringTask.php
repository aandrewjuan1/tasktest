<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTask extends Model
{
    protected $fillable = [
        'task_id',
        'recurrence_type',
        'interval',
        'start_date',
        'end_date',
        'days_of_week',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_type' => RecurrenceType::class,
            'start_date' => 'date',
            'end_date' => 'date',
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
}
