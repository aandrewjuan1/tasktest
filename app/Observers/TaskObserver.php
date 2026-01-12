<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Events\TaskCreated;
use App\Events\TaskDeleted;
use App\Events\TaskUpdated;
use App\Models\Task;

class TaskObserver
{
    public function updating(Task $task): void
    {
        // Set completed_at when status changes to Done
        if ($task->isDirty('status') && $task->status === TaskStatus::Done) {
            $task->completed_at = now();
        } elseif ($task->isDirty('status') && $task->status !== TaskStatus::Done) {
            // Clear completed_at if status changes away from Done
            $task->completed_at = null;
        }
    }

    public function created(Task $task): void
    {
        TaskCreated::dispatch($task);
    }

    public function updated(Task $task): void
    {
        TaskUpdated::dispatch($task);
    }

    public function deleted(Task $task): void
    {
        TaskDeleted::dispatch($task);
    }
}
