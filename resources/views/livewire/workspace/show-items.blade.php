<?php

use App\Enums\EventStatus;
use App\Enums\RecurrenceType;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventInstance;
use App\Models\Project;
use App\Models\RecurringEvent;
use App\Models\RecurringTask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskInstance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // View and display options
    #[Url(except: 'list')]
    public string $viewMode = 'list'; // list, kanban, daily-timegrid, weekly-timegrid

    // Timegrid view properties
    public ?Carbon $weekStartDate = null;

    // Date navigation for list and kanban views
    public ?Carbon $currentDate = null;

    // Filter properties
    #[Url]
    public ?string $filterType = null;
    #[Url]
    public ?string $filterPriority = null;
    #[Url]
    public ?string $filterStatus = null;
    public array $filterTagIds = [];

    // Sort properties
    #[Url]
    public ?string $sortBy = null;
    #[Url]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->weekStartDate = now()->startOfWeek();
        $this->currentDate = now();
        $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
        // Reset filters and sorts when switching views
        $this->clearAll();
    }

    public function goToTodayDate(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            $this->currentDate = now();
            $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
        }
    }

    public function previousDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->subDay();
            $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
        }
    }

    public function nextDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->addDay();
            $this->dispatch('date-focused', date: $this->currentDate->format('Y-m-d'));
        }
    }

    #[On('switch-to-day-view')]
    public function switchToDayView(string $date): void
    {
        // This method is kept for backward compatibility
        // Calendar now dispatches date-focused directly, but other components might still use this
        $this->viewMode = 'list';
        $this->currentDate = Carbon::parse($date);
    }

    #[On('date-focused')]
    public function updateCurrentDate(string $date): void
    {
        $parsedDate = Carbon::parse($date);

        // Update the appropriate date property based on current view mode
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            $this->currentDate = $parsedDate;
        } elseif ($this->viewMode === 'weekly-timegrid') {
            // For weekly timegrid view, update to the start of the week containing the clicked date
            $this->weekStartDate = $parsedDate->copy()->startOfWeek();
        } elseif ($this->viewMode === 'daily-timegrid') {
            // For daily timegrid view, update the current date
            $this->currentDate = $parsedDate;
        }
    }

    #[On('switch-to-daily-timegrid')]
    public function switchToDailyTimegrid(?string $date = null): void
    {
        $this->viewMode = 'daily-timegrid';
        if ($date) {
            $this->currentDate = Carbon::parse($date);
        } else {
            $this->currentDate = $this->currentDate ?? now();
        }
    }

    #[On('switch-to-weekly-timegrid')]
    public function switchToWeeklyTimegrid(?string $weekStart = null): void
    {
        $this->viewMode = 'weekly-timegrid';
        if ($weekStart) {
            $this->weekStartDate = Carbon::parse($weekStart);
        } else {
            $this->weekStartDate = $this->weekStartDate ?? now()->startOfWeek();
        }
    }

    #[On('switch-to-timegrid-day')]
    public function switchToTimegridDay(string $date): void
    {
        // Legacy support - redirect to daily timegrid
        $this->switchToDailyTimegrid($date);
    }

    #[On('switch-to-timegrid-week')]
    public function switchToTimegridWeek(string $weekStart): void
    {
        // Legacy support - redirect to weekly timegrid
        $this->switchToWeeklyTimegrid($weekStart);
    }

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        // Legacy support - redirect to weekly timegrid
        $this->switchToWeeklyTimegrid($weekStart);
    }

    #[On('update-item-status')]
    public function handleUpdateItemStatus(int $itemId, string $itemType, string $newStatus, ?string $instanceDate = null): void
    {
        $this->updateItemStatus($itemId, $itemType, $newStatus, $instanceDate);
    }

    #[On('update-item-datetime')]
    public function handleUpdateItemDateTime(int $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        $this->updateItemDateTime($itemId, $itemType, $newStart, $newEnd);
    }

    #[On('update-item-duration')]
    public function handleUpdateItemDuration(int $itemId, string $itemType, int $newDurationMinutes): void
    {
        $this->updateItemDuration($itemId, $itemType, $newDurationMinutes);
    }

    #[On('delete-task')]
    public function handleDeleteTask(int $taskId): void
    {
        $this->deleteTask($taskId);
    }

    public function deleteTask(int $taskId): void
    {
        try {
            $task = Task::findOrFail($taskId);
            $this->authorize('delete', $task);

            DB::transaction(function () use ($task) {
                $task->delete();
            });

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Task deleted successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to delete task from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $taskId,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete task. Please try again.', type: 'error');
        }
    }

    #[On('reset-filters-sorts')]
    public function resetFiltersAndSorts(): void
    {
        $this->clearAll();
    }

    #[On('set-filter-type')]
    public function handleSetFilterType(...$params): void
    {
        $type = $params[0] ?? null;
        $this->setFilterType($type);
    }

    #[On('set-filter-priority')]
    public function handleSetFilterPriority(...$params): void
    {
        $priority = $params[0] ?? null;
        $this->setFilterPriority($priority);
    }

    #[On('set-filter-status')]
    public function handleSetFilterStatus(...$params): void
    {
        $status = $params[0] ?? null;
        $this->setFilterStatus($status);
    }

    #[On('set-sort-by')]
    public function handleSetSortBy(...$params): void
    {
        $sortBy = $params[0] ?? null;
        $this->setSortBy($sortBy);
    }

    #[On('clear-all-filters-sorts')]
    public function handleClearAll(): void
    {
        $this->clearAll();
    }

    public function setFilterType(?string $type): void
    {
        $this->filterType = $type;
        // Livewire will automatically recompute computed properties
    }

    public function setFilterPriority(?string $priority): void
    {
        $this->filterPriority = $priority;
        // Livewire will automatically recompute computed properties
    }

    public function setFilterStatus(?string $status): void
    {
        $this->filterStatus = $status;
        // Livewire will automatically recompute computed properties
    }

    public function setSortBy(?string $sortBy): void
    {
        if ($this->sortBy === $sortBy) {
            // Toggle direction if same sort field
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $sortBy;
            $this->sortDirection = 'asc';
        }
        // Livewire will automatically recompute computed properties
    }

    public function clearFilters(): void
    {
        $this->filterType = null;
        $this->filterPriority = null;
        $this->filterStatus = null;
        $this->filterTagIds = [];
        // Livewire will automatically recompute computed properties
    }

    public function clearSorting(): void
    {
        $this->sortBy = null;
        $this->sortDirection = 'asc';
        // Livewire will automatically recompute computed properties
    }

    public function clearAll(): void
    {
        $this->clearFilters();
        $this->clearSorting();
    }

    #[On('update-task-field')]
    public function handleUpdateTaskField(int $taskId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        $this->updateTaskField($taskId, $field, $value, $instanceDate);
    }

    #[On('update-task-tags')]
    public function handleUpdateTaskTags(int $itemId, string $itemType, array $tagIds): void
    {
        $this->updateTaskTags($itemId, $itemType, $tagIds);
    }

    #[On('update-event-field')]
    public function handleUpdateEventField(int $eventId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        $this->updateEventField($eventId, $field, $value, $instanceDate);
    }

    #[On('delete-event')]
    public function handleDeleteEvent(int $eventId): void
    {
        $this->deleteEvent($eventId);
    }

    #[On('update-project-field')]
    public function handleUpdateProjectField(int $projectId, string $field, mixed $value): void
    {
        $this->updateProjectField($projectId, $field, $value);
    }

    #[On('delete-project')]
    public function handleDeleteProject(int $projectId): void
    {
        $this->deleteProject($projectId);
    }

    public function createTask(array $data): void
    {
        $this->authorize('create', Task::class);

        try {
            $validated = validator($data, [
                'title' => ['required', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'in:to_do,doing,done'],
                'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
                'complexity' => ['nullable', 'string', 'in:simple,moderate,complex'],
                'duration' => ['nullable', 'integer', 'min:1'],
                'startDatetime' => ['nullable', 'date'],
                'endDatetime' => ['nullable', 'date', 'after_or_equal:startDatetime'],
                'projectId' => ['nullable', 'exists:projects,id'],
                'tagIds' => ['nullable', 'array'],
                'tagIds.*' => ['integer', 'exists:tags,id'],
                'recurrence' => ['nullable', 'array'],
                'recurrence.enabled' => ['nullable', 'boolean'],
                'recurrence.type' => ['nullable', 'required_if:recurrence.enabled,true', 'string', 'in:daily,weekly,monthly,yearly,custom'],
                'recurrence.interval' => ['nullable', 'required_if:recurrence.enabled,true', 'integer', 'min:1'],
                'recurrence.daysOfWeek' => ['nullable', 'array'],
                'recurrence.daysOfWeek.*' => ['integer', 'min:0', 'max:6'],
            ], [], [
                'title' => 'title',
                'status' => 'status',
                'priority' => 'priority',
                'complexity' => 'complexity',
                'duration' => 'duration',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
                'projectId' => 'project',
                'recurrence.type' => 'recurrence type',
                'recurrence.interval' => 'recurrence interval',
            ])->validate();

            $startDatetime = ! empty($validated['startDatetime'])
                ? Carbon::parse($validated['startDatetime'])
                : null;

            $endDatetime = ! empty($validated['endDatetime'])
                ? Carbon::parse($validated['endDatetime'])
                : null;

            DB::transaction(function () use ($validated, $startDatetime, $endDatetime) {
                $task = Task::create([
                    'user_id' => auth()->id(),
                    'title' => $validated['title'],
                    'status' => $validated['status'] ? TaskStatus::from($validated['status']) : null,
                    'priority' => $validated['priority'] ? TaskPriority::from($validated['priority']) : null,
                    'complexity' => $validated['complexity'] ? TaskComplexity::from($validated['complexity']) : null,
                    'duration' => $validated['duration'] ?? null,
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                    'project_id' => $validated['projectId'] ?? null,
                ]);

                if (! empty($validated['tagIds'])) {
                    $task->tags()->attach($validated['tagIds']);
                }

                // Create recurring task if enabled
                if (! empty($validated['recurrence']['enabled']) && $validated['recurrence']['enabled']) {
                    RecurringTask::create([
                        'task_id' => $task->id,
                        'recurrence_type' => RecurrenceType::from($validated['recurrence']['type']),
                        'interval' => $validated['recurrence']['interval'] ?? 1,
                        'start_datetime' => $task->start_datetime ?? now(),
                        'end_datetime' => $task->end_datetime,
                        'days_of_week' => ! empty($validated['recurrence']['daysOfWeek'])
                            ? implode(',', $validated['recurrence']['daysOfWeek'])
                            : null,
                    ]);
                }
            });

            $this->dispatch('notify', message: 'Task created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Task creation validation failed', [
                'user_id' => auth()->id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create task', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'input_data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to create task. Please try again.', type: 'error');
        }
    }

    public function updateTaskField(int $taskId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        try {
            $task = Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                ->findOrFail($taskId);

            $this->authorize('update', $task);

            $validationRules = $this->taskFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            // Handle nested validation for recurrence
            if ($field === 'recurrence') {
                // If value is null, it means recurrence is being disabled - no validation needed
                if ($value === null) {
                    // Skip validation for null values (disabling recurrence)
                } else {
                    $rules = [
                        'recurrence' => ['nullable', 'array'],
                        'recurrence.enabled' => ['nullable', 'boolean'],
                        'recurrence.type' => ['nullable', 'required_if:recurrence.enabled,true', 'string', 'in:daily,weekly,monthly,yearly,custom'],
                        'recurrence.interval' => ['nullable', 'required_if:recurrence.enabled,true', 'integer', 'min:1'],
                        'recurrence.daysOfWeek' => ['nullable', 'array'],
                        'recurrence.daysOfWeek.*' => ['integer', 'min:0', 'max:6'],
                        'recurrence.startDatetime' => ['nullable', 'date'],
                        'recurrence.endDatetime' => ['nullable', 'date', 'after_or_equal:recurrence.startDatetime'],
                    ];
                    validator(
                        [$field => $value],
                        $rules,
                        [],
                        [
                            'recurrence.type' => 'recurrence type',
                            'recurrence.interval' => 'recurrence interval',
                        ],
                    )->validate();
                }
            } else {
                validator(
                    [$field => $value],
                    [$field => $validationRules],
                    [],
                    [$field => $field],
                )->validate();
            }

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

            // No need to dispatch 'task-updated' - Livewire will automatically re-render
            // after this method completes, and computed properties will recompute.
            // We only dispatch 'item-updated' for other components that might listen to it.
            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update task field from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'task_id' => $taskId,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update task. Please try again.', type: 'error');
        }
    }

    public function updateTaskTags(int $itemId, string $itemType, array $tagIds): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($itemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($itemId),
                'project' => Project::with(['tags', 'tasks', 'reminders'])
                    ->findOrFail($itemId),
                default => throw new \InvalidArgumentException("Invalid item type: {$itemType}"),
            };

            $this->authorize('update', $model);

            validator(
                ['tagIds' => $tagIds],
                [
                    'tagIds' => ['nullable', 'array'],
                    'tagIds.*' => ['integer', 'exists:tags,id'],
                ],
                [],
                ['tagIds' => 'tags'],
            )->validate();

            DB::transaction(function () use ($model, $tagIds) {
                $model->tags()->sync($tagIds);
                $model->refresh();
            });

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update tags from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update tags. Please try again.', type: 'error');
        }
    }

    public function updateEventField(int $eventId, string $field, mixed $value, ?string $instanceDate = null): void
    {
        try {
            $event = Event::with(['tags', 'reminders', 'recurringEvent'])
                ->findOrFail($eventId);

            $this->authorize('update', $event);

            $validationRules = $this->eventFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            validator(
                [$field => $value],
                [$field => $validationRules],
                [],
                [$field => $field],
            )->validate();

            DB::transaction(function () use ($event, $field, $value, $instanceDate) {
                // Handle status updates for recurring events with instance_date
                if ($field === 'status' && $event->recurringEvent && $instanceDate) {
                    $statusEnum = match ($value) {
                        'to_do', 'scheduled' => EventStatus::Scheduled,
                        'doing', 'ongoing' => EventStatus::Ongoing,
                        'done', 'completed' => EventStatus::Completed,
                        'cancelled' => EventStatus::Cancelled,
                        'tentative' => EventStatus::Tentative,
                        default => null,
                    };

                    if ($statusEnum) {
                        // Parse date and normalize
                        $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                        $dateString = $parsedDate->format('Y-m-d');

                        // Check if instance exists for this specific event_id and date
                        $existingInstance = EventInstance::where('event_id', $event->id)
                            ->whereDate('instance_date', $dateString)
                            ->first();

                        if ($existingInstance) {
                            // Update existing instance
                            $existingInstance->update([
                                'status' => $statusEnum,
                                'cancelled' => $statusEnum === EventStatus::Cancelled,
                                'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                            ]);
                        } else {
                            // Create new instance
                            EventInstance::create([
                                'recurring_event_id' => $event->recurringEvent->id,
                                'event_id' => $event->id,
                                'instance_date' => $dateString,
                                'status' => $statusEnum,
                                'cancelled' => $statusEnum === EventStatus::Cancelled,
                                'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                            ]);
                        }
                    }

                    return; // Don't update base event for instance status changes
                }

                $updateData = [];

                switch ($field) {
                    case 'title':
                        $updateData['title'] = $value;
                        break;
                    case 'description':
                        $updateData['description'] = $value ?: null;
                        break;
                    case 'startDatetime':
                        if ($value) {
                            $startDatetime = Carbon::parse($value);
                            $updateData['start_datetime'] = $startDatetime;
                            if (! $event->end_datetime) {
                                $updateData['end_datetime'] = $startDatetime->copy()->addHour();
                            }
                        } else {
                            $updateData['start_datetime'] = null;
                        }
                        break;
                    case 'endDatetime':
                        $updateData['end_datetime'] = $value ? Carbon::parse($value) : null;
                        break;
                    case 'status':
                        $updateData['status'] = $value ?: 'scheduled';
                        break;
                }

                if (! empty($updateData)) {
                    $event->update($updateData);
                }
            });

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update event field from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'event_id' => $eventId,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update event. Please try again.', type: 'error');
        }
    }

    public function deleteEvent(int $eventId): void
    {
        try {
            $event = Event::findOrFail($eventId);
            $this->authorize('delete', $event);

            DB::transaction(function () use ($event) {
                $event->delete();
            });

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Event deleted successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to delete event from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_id' => $eventId,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete event. Please try again.', type: 'error');
        }
    }

    public function updateProjectField(int $projectId, string $field, mixed $value): void
    {
        try {
            $project = Project::with(['tags', 'tasks', 'reminders'])
                ->findOrFail($projectId);

            $this->authorize('update', $project);

            $validationRules = $this->projectFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            validator(
                [$field => $value],
                [$field => $validationRules],
                [],
                [$field => $field],
            )->validate();

            DB::transaction(function () use ($project, $field, $value) {
                $updateData = [];

                switch ($field) {
                    case 'name':
                        $updateData['name'] = $value;
                        break;
                    case 'description':
                        $updateData['description'] = $value ?: null;
                        break;
                    case 'startDatetime':
                        $updateData['start_datetime'] = $value ? Carbon::parse($value) : null;
                        break;
                    case 'endDatetime':
                        $updateData['end_datetime'] = $value ? Carbon::parse($value) : null;
                        break;
                }

                if (! empty($updateData)) {
                    $project->update($updateData);
                }
            });

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update project field from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'field' => $field,
                'project_id' => $projectId,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to update project. Please try again.', type: 'error');
        }
    }

    public function deleteProject(int $projectId): void
    {
        try {
            $project = Project::findOrFail($projectId);
            $this->authorize('delete', $project);

            DB::transaction(function () use ($project) {
                $project->delete();
            });

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Project deleted successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to delete project from parent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to delete project. Please try again.', type: 'error');
        }
    }

    public function createEvent(array $data): void
    {
        $this->authorize('create', Event::class);

        try {
            $validated = validator($data, [
                'title' => ['required', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'in:scheduled,cancelled,completed,tentative,ongoing'],
                'startDatetime' => ['nullable', 'date'],
                'endDatetime' => ['nullable', 'date', 'after:startDatetime'],
                'tagIds' => ['nullable', 'array'],
                'tagIds.*' => ['integer', 'exists:tags,id'],
                'recurrence' => ['nullable', 'array'],
                'recurrence.enabled' => ['nullable', 'boolean'],
                'recurrence.type' => ['nullable', 'required_if:recurrence.enabled,true', 'string', 'in:daily,weekly,monthly,yearly,custom'],
                'recurrence.interval' => ['nullable', 'required_if:recurrence.enabled,true', 'integer', 'min:1'],
                'recurrence.daysOfWeek' => ['nullable', 'array'],
                'recurrence.daysOfWeek.*' => ['integer', 'min:0', 'max:6'],
            ], [], [
                'title' => 'title',
                'status' => 'status',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
                'recurrence.type' => 'recurrence type',
                'recurrence.interval' => 'recurrence interval',
            ])->validate();

            $startDatetime = ! empty($validated['startDatetime'])
                ? Carbon::parse($validated['startDatetime'])
                : null;

            $endDatetime = ! empty($validated['endDatetime'])
                ? Carbon::parse($validated['endDatetime'])
                : null;

            DB::transaction(function () use ($validated, $startDatetime, $endDatetime) {
                $event = Event::create([
                    'user_id' => auth()->id(),
                    'title' => $validated['title'],
                    'status' => $validated['status'] ? EventStatus::from($validated['status']) : null,
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                ]);

                if (! empty($validated['tagIds'])) {
                    $event->tags()->attach($validated['tagIds']);
                }

                // Create recurring event if enabled
                if (! empty($validated['recurrence']['enabled']) && $validated['recurrence']['enabled']) {
                    RecurringEvent::create([
                        'event_id' => $event->id,
                        'recurrence_type' => RecurrenceType::from($validated['recurrence']['type']),
                        'interval' => $validated['recurrence']['interval'] ?? 1,
                        'start_datetime' => $event->start_datetime ?? now(),
                        'end_datetime' => $event->end_datetime,
                        'days_of_week' => ! empty($validated['recurrence']['daysOfWeek'])
                            ? implode(',', $validated['recurrence']['daysOfWeek'])
                            : null,
                    ]);
                }
            });

            $this->dispatch('notify', message: 'Event created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Event creation validation failed', [
                'user_id' => auth()->id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create event', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'input_data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to create event. Please try again.', type: 'error');
        }
    }

    public function createProject(array $data): void
    {
        $this->authorize('create', Project::class);

        try {
            $validated = validator($data, [
                'name' => ['required', 'string', 'max:255'],
                'startDatetime' => ['nullable', 'date'],
                'endDatetime' => ['nullable', 'date', 'after_or_equal:startDatetime'],
                'tagIds' => ['nullable', 'array'],
                'tagIds.*' => ['integer', 'exists:tags,id'],
            ], [], [
                'name' => 'name',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
            ])->validate();

            $startDatetime = ! empty($validated['startDatetime'])
                ? Carbon::parse($validated['startDatetime'])
                : null;

            $endDatetime = ! empty($validated['endDatetime'])
                ? Carbon::parse($validated['endDatetime'])
                : null;

            DB::transaction(function () use ($validated, $startDatetime, $endDatetime) {
                $project = Project::create([
                    'user_id' => auth()->id(),
                    'name' => $validated['name'],
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                ]);

                if (! empty($validated['tagIds'])) {
                    $project->tags()->attach($validated['tagIds']);
                }
            });

            $this->dispatch('notify', message: 'Project created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Project creation validation failed', [
                'user_id' => auth()->id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'input_data' => $data,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to create project. Please try again.', type: 'error');
        }
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return ($this->filterType && $this->filterType !== 'all')
            || ($this->filterPriority && $this->filterPriority !== 'all')
            || ($this->filterStatus && $this->filterStatus !== 'all')
            || ! empty($this->filterTagIds);
    }

    #[Computed]
    public function availableTags(): Collection
    {
        return Tag::orderBy('name')->get();
    }

    public function createTag(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'Tag name cannot be empty'];
        }

        try {
            $existing = Tag::whereRaw('LOWER(name) = LOWER(?)', [$name])->first();

            if ($existing) {
                if (! in_array($existing->id, $this->filterTagIds, true)) {
                    $this->filterTagIds[] = $existing->id;
                }

                return [
                    'success' => true,
                    'tagId' => $existing->id,
                    'tagName' => $existing->name,
                    'alreadyExists' => true,
                ];
            }

            $tag = Tag::create(['name' => $name]);
            $this->filterTagIds[] = $tag->id;

            return [
                'success' => true,
                'tagId' => $tag->id,
                'tagName' => $tag->name,
                'alreadyExists' => false,
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to create tag', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'tag_name' => $name,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['success' => false, 'message' => 'Failed to create tag'];
        }
    }

    public function deleteTag(int $tagId): array
    {
        try {
            $tag = Tag::findOrFail($tagId);
            $tag->delete();

            $this->filterTagIds = array_values(
                array_filter($this->filterTagIds, fn ($id) => (int) $id !== $tagId)
            );

            return ['success' => true];
        } catch (\Exception $e) {
            \Log::error('Failed to delete tag', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'tag_id' => $tagId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['success' => false, 'message' => 'Failed to delete tag'];
        }
    }

    public function updateItemDateTime(int $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($itemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($itemId),
                'project' => Project::with(['tags', 'tasks', 'reminders'])
                    ->findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            DB::transaction(function () use ($model, $itemType, $newStart, $newEnd) {
                if ($itemType === 'task') {
                    if ($newStart) {
                        $model->start_datetime = Carbon::parse($newStart);
                    } else {
                        $model->start_datetime = null;
                    }

                    if ($newEnd) {
                        $model->end_datetime = Carbon::parse($newEnd);
                        // Calculate duration from start and end times
                        if ($model->start_datetime) {
                            $model->duration = $model->start_datetime->diffInMinutes($model->end_datetime);
                        }
                    } else {
                        $model->end_datetime = null;
                    }
                } elseif ($itemType === 'event') {
                    if ($newStart) {
                        $model->start_datetime = Carbon::parse($newStart);
                    }
                    if ($newEnd) {
                        $model->end_datetime = Carbon::parse($newEnd);
                    } elseif ($newStart) {
                        // Auto-calculate if start provided but no end
                        $model->end_datetime = Carbon::parse($newStart)->addHour();
                    }
                } elseif ($itemType === 'project') {
                    if ($newStart) {
                        $model->start_datetime = Carbon::parse($newStart);
                    } else {
                        $model->start_datetime = null;
                    }
                    if ($newEnd) {
                        $model->end_datetime = Carbon::parse($newEnd);
                    } else {
                        $model->end_datetime = null;
                    }
                }

                $model->save();
            });

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Item updated successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to update item datetime', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to update item. Please try again.', type: 'error');
        }
    }

    public function updateItemDuration(int $itemId, string $itemType, int $newDurationMinutes): void
    {
        try {
            // Enforce minimum duration of 30 minutes
            $newDurationMinutes = max(30, $newDurationMinutes);

            // Snap to 30-minute grid intervals
            $newDurationMinutes = round($newDurationMinutes / 30) * 30;
            $newDurationMinutes = max(30, $newDurationMinutes); // Ensure still at least 30 after snapping

            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($itemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            DB::transaction(function () use ($model, $itemType, $newDurationMinutes) {
                if ($itemType === 'task') {
                    // For tasks, update duration and recalculate end_datetime if needed
                    $model->duration = $newDurationMinutes;

                    if ($model->start_datetime) {
                        $endDateTime = $model->start_datetime->copy()->addMinutes($newDurationMinutes);
                        $model->end_datetime = $endDateTime;
                    }
                } elseif ($itemType === 'event') {
                    // For events, update end_datetime while keeping start_datetime
                    $startDateTime = Carbon::parse($model->start_datetime);
                    $model->end_datetime = $startDateTime->copy()->addMinutes($newDurationMinutes);
                }

                $model->save();
            });

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Duration updated successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to update item duration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to update duration. Please try again.', type: 'error');
        }
    }

    protected function taskFieldRules(string $field): array
    {
        $statusValues = array_map(fn ($c) => $c->value, TaskStatus::cases());
        $priorityValues = array_map(fn ($c) => $c->value, TaskPriority::cases());
        $complexityValues = array_map(fn ($c) => $c->value, TaskComplexity::cases());

        return match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            'priority' => ['nullable', 'string', Rule::in($priorityValues)],
            'complexity' => ['nullable', 'string', Rule::in($complexityValues)],
            'duration' => ['nullable', 'integer', 'min:1'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date'],
            'projectId' => ['nullable', 'exists:projects,id'],
            'recurrence' => [
                'nullable',
                'array',
            ],
            default => [],
        };
    }

    protected function eventFieldRules(string $field): array
    {
        $statusValues = array_map(fn ($c) => $c->value, EventStatus::cases());

        return match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            default => [],
        };
    }

    protected function projectFieldRules(string $field): array
    {
        return match ($field) {
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDatetime' => ['nullable', 'date'],
            'endDatetime' => ['nullable', 'date', 'after_or_equal:startDatetime'],
            default => [],
        };
    }

    public function updateItemStatus(int $itemId, string $itemType, string $newStatus, ?string $instanceDate = null): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($itemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($itemId),
                'project' => Project::with(['tags', 'tasks', 'reminders'])
                    ->findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            // Map kanban column status to appropriate enum value for each item type
            if ($itemType === 'task') {
                $statusEnum = match ($newStatus) {
                    'to_do' => TaskStatus::ToDo,
                    'doing' => TaskStatus::Doing,
                    'done' => TaskStatus::Done,
                    default => null,
                };

                if ($statusEnum) {
                    DB::transaction(function () use ($model, $statusEnum, $instanceDate) {
                        // Handle status updates for recurring tasks with instance_date
                        if ($model->recurringTask && $instanceDate) {
                            // Parse date and normalize
                            $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                            $dateString = $parsedDate->format('Y-m-d');

                            // Check if instance exists for this specific task_id, recurring_task_id and date
                            // Use latest() to get the most recent instance if duplicates exist
                            $existingInstance = TaskInstance::where('task_id', $model->id)
                                ->where('recurring_task_id', $model->recurringTask->id)
                                ->whereDate('instance_date', $dateString)
                                ->latest('id')
                                ->first();

                            if ($existingInstance) {
                                // Update existing instance
                                $existingInstance->update([
                                    'task_id' => $model->id,
                                    'status' => $statusEnum,
                                    'completed_at' => $statusEnum === TaskStatus::Done ? now() : null,
                                ]);
                            } else {
                                // Create new instance
                                TaskInstance::create([
                                    'recurring_task_id' => $model->recurringTask->id,
                                    'task_id' => $model->id,
                                    'instance_date' => $dateString,
                                    'status' => $statusEnum,
                                    'completed_at' => $statusEnum === TaskStatus::Done ? now() : null,
                                ]);
                            }
                        } else {
                            // Update base task for non-recurring tasks or when no instance date provided
                            $model->update(['status' => $statusEnum]);
                        }
                    });

                    $this->dispatch('item-updated');
                    $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
                }
            } elseif ($itemType === 'event') {
                $statusEnum = match ($newStatus) {
                    'to_do' => EventStatus::Scheduled,
                    'doing' => EventStatus::Ongoing,
                    'done' => EventStatus::Completed,
                    'scheduled' => EventStatus::Scheduled,
                    'ongoing' => EventStatus::Ongoing,
                    'completed' => EventStatus::Completed,
                    'cancelled' => EventStatus::Cancelled,
                    'tentative' => EventStatus::Tentative,
                    default => null,
                };

                if ($statusEnum) {
                    DB::transaction(function () use ($model, $statusEnum, $instanceDate) {
                        // Handle status updates for recurring events with instance_date
                        if ($model->recurringEvent && $instanceDate) {
                            // Parse date and normalize
                            $parsedDate = Carbon::parse($instanceDate)->startOfDay();
                            $dateString = $parsedDate->format('Y-m-d');

                            // Check if instance exists for this specific event_id and date
                            $existingInstance = EventInstance::where('event_id', $model->id)
                                ->whereDate('instance_date', $dateString)
                                ->first();

                            if ($existingInstance) {
                                // Update existing instance
                                $existingInstance->update([
                                    'status' => $statusEnum,
                                    'cancelled' => $statusEnum === EventStatus::Cancelled,
                                    'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                                ]);
                            } else {
                                // Create new instance
                                EventInstance::create([
                                    'recurring_event_id' => $model->recurringEvent->id,
                                    'event_id' => $model->id,
                                    'instance_date' => $dateString,
                                    'status' => $statusEnum,
                                    'cancelled' => $statusEnum === EventStatus::Cancelled,
                                    'completed_at' => $statusEnum === EventStatus::Completed ? now() : null,
                                ]);
                            }
                        } else {
                            // Update base event for non-recurring events or when no instance date provided
                            $model->update(['status' => $statusEnum]);
                        }
                    });

                    $this->dispatch('item-updated');
                    $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
                }
            }
            // Projects don't have status, so no update needed
        } catch (\Exception $e) {
            \Log::error('Failed to update item status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_id' => $itemId,
                'item_type' => $itemType,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->dispatch('notify', message: 'Failed to update status. Please try again.', type: 'error');
        }
    }

    /**
     * Get the date range for the current view mode.
     * Used to determine which date range to generate instances for.
     */
    protected function getDateRangeForView(): array
    {
        if (in_array($this->viewMode, ['list', 'kanban'])) {
            // For list/kanban views, use the current date (single day)
            $date = $this->currentDate ?? now();
            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $date->copy()->endOfDay(),
            ];
        } elseif ($this->viewMode === 'daily-timegrid') {
            // For daily timegrid, use the current date
            $date = $this->currentDate ?? now();
            return [
                'start' => $date->copy()->startOfDay(),
                'end' => $date->copy()->endOfDay(),
            ];
        } elseif ($this->viewMode === 'weekly-timegrid') {
            // For weekly timegrid, use the week range
            $weekStart = $this->weekStartDate ?? now()->startOfWeek();
            return [
                'start' => $weekStart->copy()->startOfDay(),
                'end' => $weekStart->copy()->endOfWeek()->endOfDay(),
            ];
        }

        // Default: current day
        $date = now();
        return [
            'start' => $date->copy()->startOfDay(),
            'end' => $date->copy()->endOfDay(),
        ];
    }

    /**
     * Sort a collection of items based on the current sort settings.
     */
    protected function sortCollection(Collection $items): Collection
    {
        if (! $this->sortBy) {
            return $items->sortBy('created_at', SORT_REGULAR, $this->sortDirection === 'desc');
        }

        return $items->sort(function ($a, $b) {
            $direction = $this->sortDirection === 'desc' ? -1 : 1;

            return match ($this->sortBy) {
                'priority' => $direction * $this->comparePriority($a->priority ?? null, $b->priority ?? null),
                'created_at' => $direction * ($a->created_at <=> $b->created_at),
                'start_datetime' => $direction * ($a->start_datetime <=> $b->start_datetime),
                'end_datetime' => $direction * ($a->end_datetime <=> $b->end_datetime),
                'title' => $direction * strcasecmp($a->title ?? '', $b->title ?? ''),
                'status' => $direction * strcasecmp($a->status?->value ?? '', $b->status?->value ?? ''),
                default => $direction * ($a->created_at <=> $b->created_at),
            };
        })->values();
    }

    /**
     * Compare two priority values for sorting.
     */
    protected function comparePriority($priorityA, $priorityB): int
    {
        $priorityOrder = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $valueA = $priorityOrder[$priorityA?->value ?? ''] ?? 0;
        $valueB = $priorityOrder[$priorityB?->value ?? ''] ?? 0;

        return $valueA <=> $valueB;
    }

    #[Computed]
    public function filteredTasks(): Collection
    {
        $user = auth()->user();

        // Determine date range for instance generation
        $dateRange = $this->getDateRangeForView();

        $query = Task::query()
            ->accessibleBy($user)
            ->with(['project.tasks', 'tags', 'event', 'recurringTask']);

        // Apply filters (but not date filter - we'll handle that with instances)
        if ($this->filterType === 'task' || !$this->filterType || $this->filterType === 'all') {
            if ($this->filterPriority) {
                $query->filterByPriority($this->filterPriority);
            }
            if ($this->filterStatus) {
                $query->filterByStatus($this->filterStatus);
            }
            if (! empty($this->filterTagIds)) {
                $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
            }
        } else {
            return collect();
        }

        // Get all tasks (without date filtering)
        $tasks = $query->get();

        // Generate instances for each task within the date range
        $allInstances = collect();
        foreach ($tasks as $task) {
            $instances = $task->getInstancesForDateRange($dateRange['start'], $dateRange['end']);

            // Apply filters to instances
            $filteredInstances = $instances->filter(function ($instance) {
                // Filter by status if set
                if ($this->filterStatus && $this->filterStatus !== 'all') {
                    if ($instance->status?->value !== $this->filterStatus) {
                        return false;
                    }
                }

                // Filter by priority if set
                if ($this->filterPriority && $this->filterPriority !== 'all') {
                    if ($instance->priority?->value !== $this->filterPriority) {
                        return false;
                    }
                }

                // Filter by tags if set
                if (! empty($this->filterTagIds)) {
                    $instanceTagIds = $instance->tags->pluck('id')->toArray();
                    if (empty(array_intersect($this->filterTagIds, $instanceTagIds))) {
                        return false;
                    }
                }

                // For timegrid views, only show items with at least one date
                if (in_array($this->viewMode, ['daily-timegrid', 'weekly-timegrid'])) {
                    return $instance->start_datetime || $instance->end_datetime;
                }

                return true;
            });

            $allInstances = $allInstances->merge($filteredInstances);
        }

        // Apply sorting
        return $this->sortCollection($allInstances)
            ->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_datetime ?? $task->start_datetime ?? $task->created_at;

                return $task;
            });
    }

    #[Computed]
    public function filteredEvents(): Collection
    {
        $user = auth()->user();

        // Determine date range for instance generation
        $dateRange = $this->getDateRangeForView();

        $query = Event::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks', 'recurringEvent']);

        // Apply filters (but not date filter - we'll handle that with instances)
        if ($this->filterType === 'event' || !$this->filterType || $this->filterType === 'all') {
            if ($this->filterPriority) {
                $query->filterByPriority($this->filterPriority);
            }
            if ($this->filterStatus) {
                $query->filterByStatus($this->filterStatus);
            }
            if (! empty($this->filterTagIds)) {
                $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
            }
        } else {
            return collect();
        }

        // Get all events (without date filtering)
        $events = $query->get();

        // Generate instances for each event within the date range
        $allInstances = collect();
        foreach ($events as $event) {
            $instances = $event->getInstancesForDateRange($dateRange['start'], $dateRange['end']);

            // Apply filters to instances
            $filteredInstances = $instances->filter(function ($instance) {
                // Filter by status if set
                if ($this->filterStatus && $this->filterStatus !== 'all') {
                    if ($instance->status?->value !== $this->filterStatus) {
                        return false;
                    }
                }

                // Filter by tags if set
                if (! empty($this->filterTagIds)) {
                    $instanceTagIds = $instance->tags->pluck('id')->toArray();
                    if (empty(array_intersect($this->filterTagIds, $instanceTagIds))) {
                        return false;
                    }
                }

                // Skip cancelled instances
                if (isset($instance->cancelled) && $instance->cancelled) {
                    return false;
                }

                // For timegrid views, only show items with at least one date
                if (in_array($this->viewMode, ['daily-timegrid', 'weekly-timegrid'])) {
                    return $instance->start_datetime || $instance->end_datetime;
                }

                return true;
            });

            $allInstances = $allInstances->merge($filteredInstances);
        }

        // Apply sorting
        return $this->sortCollection($allInstances)
            ->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime ?? $event->created_at;

                return $event;
            });
    }

    #[Computed]
    public function filteredItems(): Collection
    {
        // For timegrid views, use the same lazy generation approach
        if (in_array($this->viewMode, ['daily-timegrid', 'weekly-timegrid'])) {
            // Use the same methods which now handle lazy generation
            return collect()
                ->merge($this->filteredTasks)
                ->merge($this->filteredEvents);
        }

        // For list/kanban views, merge filtered collections
        return collect()
            ->merge($this->filteredTasks)
            ->merge($this->filteredEvents);
    }

    #[Computed]
    public function itemsByStatus(): array
    {
        $items = $this->filteredItems;

        return [
            'to_do' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['to_do', 'scheduled', 'tentative'])),
            'doing' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['doing', 'ongoing'])),
            'done' => $items->filter(fn ($item) => in_array($item->status?->value ?? '', ['done', 'completed'])),
        ];
    }
}; ?>
<div
    wire:key="show-items-component"
    class="space-y-4 px-4"
    wire:loading.class="opacity-50"
    wire:target="switchView,goToTodayDate,previousDay,nextDay,updateCurrentDate"
    x-data="{
        tagDropdown: {
            showCreateInput: false,
            newTagName: '',
            creating: false,
            deleting: false,
            async handleCreate() {
                if (!this.newTagName.trim() || this.creating) return;
                this.creating = true;
                const name = this.newTagName.trim();
                const response = await $wire.createTag(name);
                this.creating = false;
                if (response?.success) {
                    this.newTagName = '';
                    this.showCreateInput = false;
                }
            },
            async handleDelete(tagId) {
                if (this.deleting) return;
                this.deleting = true;
                const response = await $wire.deleteTag(tagId);
                this.deleting = false;
                if (!response?.success) return;
            }
        }
    }"
    x-cloak
>
    <!-- Loading Overlay for View Switching -->
    <div
        wire:loading
        wire:target="switchView"
        x-cloak
        class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none"
    >
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Switching view...</span>
        </div>
    </div>

    <!-- Loading Overlay for Date Navigation -->
    <div
        wire:loading
        wire:target="goToTodayDate,previousDay,nextDay,updateCurrentDate"
        x-cloak
        class="fixed inset-0 bg-black/10 dark:bg-black/20 z-40 flex items-center justify-center pointer-events-none"
    >
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 shadow-lg flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Loading date...</span>
        </div>
    </div>

    <!-- List View -->
    @if($viewMode === 'list')
        <div wire:key="list-view-container">
            <livewire:workspace.list-view
                :items="$this->filteredItems"
                :current-date="$currentDate"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                :view-mode="$viewMode"
                wire:key="list-view-{{ $viewMode }}"
            />
        </div>
    @endif

    <!-- Kanban View -->
    @if($viewMode === 'kanban')
        <div wire:key="kanban-view-container">
            <livewire:workspace.kanban-view
                :items="$this->filteredItems"
                :items-by-status="$this->itemsByStatus"
                :current-date="$currentDate"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                :view-mode="$viewMode"
                wire:key="kanban-view-{{ $viewMode }}"
            />
        </div>
    @endif

    <!-- Daily Timegrid View -->
    @if($viewMode === 'daily-timegrid')
        <div wire:key="daily-timegrid-view-container-{{ ($currentDate ?? now())->format('Y-m-d') }}">
            <livewire:workspace.daily-timegrid
                :items="$this->filteredItems"
                :current-date="$currentDate"
                :view-mode="$viewMode"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                wire:key="daily-timegrid-view-{{ ($currentDate ?? now())->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Weekly Timegrid View -->
    @if($viewMode === 'weekly-timegrid')
        <div wire:key="weekly-timegrid-view-container-{{ ($weekStartDate ?? now()->startOfWeek())->format('Y-m-d') }}">
            <livewire:workspace.weekly-timegrid
                :items="$this->filteredItems"
                :week-start-date="$weekStartDate"
                :view-mode="$viewMode"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                wire:key="weekly-timegrid-view-{{ ($weekStartDate ?? now()->startOfWeek())->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Create Item -->
    <livewire:workspace.create-item />
</div>
