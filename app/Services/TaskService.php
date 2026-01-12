<?php

namespace App\Services;

use App\Enums\RecurrenceType;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskInstance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskService
{
    public function createTask(array $data, int $userId): Task
    {
        $startDatetime = ! empty($data['startDatetime'])
            ? Carbon::parse($data['startDatetime'])
            : null;

        $endDatetime = ! empty($data['endDatetime'])
            ? Carbon::parse($data['endDatetime'])
            : null;

        return DB::transaction(function () use ($data, $startDatetime, $endDatetime, $userId) {
            $task = Task::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'status' => $data['status'] ? TaskStatus::from($data['status']) : null,
                'priority' => $data['priority'] ? TaskPriority::from($data['priority']) : null,
                'complexity' => $data['complexity'] ? TaskComplexity::from($data['complexity']) : null,
                'duration' => $data['duration'] ?? null,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'project_id' => $data['projectId'] ?? null,
            ]);

            if (! empty($data['tagIds'])) {
                $task->tags()->attach($data['tagIds']);
            }

            // Create recurring task if enabled
            if (! empty($data['recurrence']['enabled']) && $data['recurrence']['enabled']) {
                RecurringTask::create([
                    'task_id' => $task->id,
                    'recurrence_type' => RecurrenceType::from($data['recurrence']['type']),
                    'interval' => $data['recurrence']['interval'] ?? 1,
                    'start_datetime' => $task->start_datetime ?? now(),
                    'end_datetime' => $task->end_datetime,
                    'days_of_week' => ! empty($data['recurrence']['daysOfWeek'])
                        ? implode(',', $data['recurrence']['daysOfWeek'])
                        : null,
                ]);
            }

            return $task;
        });
    }

    public function updateTaskField(Task $task, string $field, mixed $value, ?string $instanceDate = null): void
    {
        DB::transaction(function () use ($task, $field, $value, $instanceDate) {
            // Handle status updates for recurring tasks with instance_date
            if ($field === 'status' && $task->recurringTask && $instanceDate) {
                $statusEnum = match ($value) {
                    'to_do' => TaskStatus::ToDo,
                    'doing' => TaskStatus::Doing,
                    'done' => TaskStatus::Done,
                    default => null,
                };

                if ($statusEnum) {
                    // Parse date and normalize
                    $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                    $dateString = $parsedDate->format('Y-m-d');

                    // Check if instance exists for this specific task_id, recurring_task_id and date
                    // Use latest() to get the most recent instance if duplicates exist
                    $existingInstance = TaskInstance::where('task_id', $task->id)
                        ->where('recurring_task_id', $task->recurringTask->id)
                        ->whereDate('instance_date', $dateString)
                        ->latest('id')
                        ->first();

                    if ($existingInstance) {
                        // Update existing instance
                        $existingInstance->update([
                            'task_id' => $task->id,
                            'status' => $statusEnum,
                            'completed_at' => $statusEnum === TaskStatus::Done ? now() : null,
                        ]);
                    } else {
                        // Create new instance
                        TaskInstance::create([
                            'recurring_task_id' => $task->recurringTask->id,
                            'task_id' => $task->id,
                            'instance_date' => $dateString,
                            'status' => $statusEnum,
                            'completed_at' => $statusEnum === TaskStatus::Done ? now() : null,
                        ]);
                    }
                }

                return; // Don't update base task for instance status changes
            }

            $updateData = [];

            switch ($field) {
                case 'title':
                    $updateData['title'] = $value;
                    break;
                case 'description':
                    $updateData['description'] = $value ?: null;
                    break;
                case 'status':
                    $updateData['status'] = $value ?: null;
                    break;
                case 'priority':
                    $updateData['priority'] = $value ?: null;
                    break;
                case 'complexity':
                    $updateData['complexity'] = $value ?: null;
                    break;
                case 'duration':
                    $updateData['duration'] = $value ?: null;
                    break;
                case 'startDatetime':
                    $updateData['start_datetime'] = $value ? Carbon::parse($value) : null;
                    break;
                case 'endDatetime':
                    $updateData['end_datetime'] = $value ? Carbon::parse($value) : null;
                    break;
                case 'projectId':
                    $updateData['project_id'] = $value ?: null;
                    break;
                case 'recurrence':
                    // Handle recurrence separately as it involves RecurringTask model
                    $recurrenceData = $value;

                    if ($recurrenceData === null || empty($recurrenceData) || ! ($recurrenceData['enabled'] ?? false)) {
                        // Delete recurrence if it exists
                        if ($task->recurringTask) {
                            $task->recurringTask->delete();
                        }
                    } else {
                        // Create or update recurrence
                        // Ensure start_datetime is never null - use fallback chain
                        $startDatetime = ! empty($recurrenceData['startDatetime'])
                            ? Carbon::parse($recurrenceData['startDatetime'])
                            : ($task->start_datetime
                                ? Carbon::parse($task->start_datetime)
                                : ($task->end_datetime
                                    ? Carbon::parse($task->end_datetime)
                                    : Carbon::now()));

                        $recurringTaskData = [
                            'task_id' => $task->id,
                            'recurrence_type' => RecurrenceType::from($recurrenceData['type']),
                            'interval' => $recurrenceData['interval'] ?? 1,
                            'start_datetime' => $startDatetime,
                            'end_datetime' => ! empty($recurrenceData['endDatetime']) ? Carbon::parse($recurrenceData['endDatetime']) : null,
                            'days_of_week' => ! empty($recurrenceData['daysOfWeek']) && is_array($recurrenceData['daysOfWeek'])
                                ? implode(',', $recurrenceData['daysOfWeek'])
                                : null,
                        ];

                        if ($task->recurringTask) {
                            $task->recurringTask->update($recurringTaskData);
                        } else {
                            RecurringTask::create($recurringTaskData);
                        }
                    }
                    break;
            }

            if (! empty($updateData)) {
                $task->update($updateData);
            }
        });
    }

    public function deleteTask(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->delete();
        });
    }

    public function updateTaskStatus(Task $task, string $status, ?string $instanceDate = null): void
    {
        $statusEnum = match ($status) {
            'to_do' => TaskStatus::ToDo,
            'doing' => TaskStatus::Doing,
            'done' => TaskStatus::Done,
            default => null,
        };

        if (! $statusEnum) {
            return;
        }

        DB::transaction(function () use ($task, $statusEnum, $instanceDate) {
            // Handle status updates for recurring tasks with instance_date
            if ($task->recurringTask && $instanceDate) {
                // Parse date and normalize
                $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                $dateString = $parsedDate->format('Y-m-d');

                // Check if instance exists for this specific task_id, recurring_task_id and date
                // Use latest() to get the most recent instance if duplicates exist
                $existingInstance = TaskInstance::where('task_id', $task->id)
                    ->where('recurring_task_id', $task->recurringTask->id)
                    ->whereDate('instance_date', $dateString)
                    ->latest('id')
                    ->first();

                if ($existingInstance) {
                    // Update existing instance
                    $existingInstance->update([
                        'task_id' => $task->id,
                        'status' => $statusEnum,
                        'completed_at' => $statusEnum === TaskStatus::Done ? now() : null,
                    ]);
                } else {
                    // Create new instance
                    TaskInstance::create([
                        'recurring_task_id' => $task->recurringTask->id,
                        'task_id' => $task->id,
                        'instance_date' => $dateString,
                        'status' => $statusEnum,
                        'completed_at' => $statusEnum === TaskStatus::Done ? now() : null,
                    ]);
                }
            } else {
                // Update base task for non-recurring tasks or when no instance date provided
                $task->update(['status' => $statusEnum]);
            }
        });
    }

    public function updateTaskDateTime(Task $task, string $start, ?string $end = null): void
    {
        DB::transaction(function () use ($task, $start, $end) {
            if ($start) {
                $task->start_datetime = Carbon::parse($start);
            } else {
                $task->start_datetime = null;
            }

            if ($end) {
                $task->end_datetime = Carbon::parse($end);
                // Calculate duration from start and end times
                if ($task->start_datetime) {
                    $task->duration = $task->start_datetime->diffInMinutes($task->end_datetime);
                }
            } else {
                $task->end_datetime = null;
            }

            $task->save();
        });
    }

    public function updateTaskDuration(Task $task, int $durationMinutes): void
    {
        // Enforce minimum duration of 30 minutes
        $durationMinutes = max(30, $durationMinutes);

        // Snap to 30-minute grid intervals
        $durationMinutes = round($durationMinutes / 30) * 30;
        $durationMinutes = max(30, $durationMinutes); // Ensure still at least 30 after snapping

        DB::transaction(function () use ($task, $durationMinutes) {
            // For tasks, update duration and recalculate end_datetime if needed
            $task->duration = $durationMinutes;

            if ($task->start_datetime) {
                $endDateTime = $task->start_datetime->copy()->addMinutes($durationMinutes);
                $task->end_datetime = $endDateTime;
            }

            $task->save();
        });
    }

    public function updateTaskTags(Task $task, array $tagIds): void
    {
        DB::transaction(function () use ($task, $tagIds) {
            $task->tags()->sync($tagIds);
            $task->refresh();
        });
    }
}
