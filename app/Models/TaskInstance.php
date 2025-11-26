<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskInstance extends Model
{
    protected $fillable = [
        'recurring_task_id',
        'task_id',
        'instance_date',
        'status',
        'overridden_title',
        'overridden_description',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'instance_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function recurringTask(): BelongsTo
    {
        return $this->belongsTo(RecurringTask::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
