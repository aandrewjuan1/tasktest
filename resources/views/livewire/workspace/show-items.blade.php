<?php

use App\Enums\EventStatus;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Services\EventService;
use App\Services\ProjectService;
use App\Services\TagService;
use App\Services\TaskService;
use App\Traits\FiltersItems;
use App\Traits\HandlesDateRanges;
use App\Traits\SortsItems;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination, FiltersItems, SortsItems, HandlesDateRanges;

    protected ?TaskService $taskService = null;

    protected ?EventService $eventService = null;

    protected ?ProjectService $projectService = null;

    protected ?TagService $tagService = null;

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

    public function mount(
        TaskService $taskService,
        EventService $eventService,
        ProjectService $projectService,
        TagService $tagService,
    ): void {
        $this->taskService = $taskService;
        $this->eventService = $eventService;
        $this->projectService = $projectService;
        $this->tagService = $tagService;

        $this->weekStartDate = now()->startOfWeek();
        $this->currentDate = now();
    }

    protected function getTaskService(): TaskService
    {
        return $this->taskService ??= app(TaskService::class);
    }

    protected function getEventService(): EventService
    {
        return $this->eventService ??= app(EventService::class);
    }

    protected function getProjectService(): ProjectService
    {
        return $this->projectService ??= app(ProjectService::class);
    }

    protected function getTagService(): TagService
    {
        return $this->tagService ??= app(TagService::class);
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
        }
    }

    public function previousDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->subDay();
        }
    }

    public function nextDay(): void
    {
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $this->currentDate = $this->currentDate->copy()->addDay();
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

            $this->getTaskService()->deleteTask($task);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Task deleted successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete task from parent', [
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
    public function handleSetFilterType(?string $type = null): void
    {
        $this->setFilterType($type);
    }

    #[On('set-filter-priority')]
    public function handleSetFilterPriority(?string $priority = null): void
    {
        $this->setFilterPriority($priority);
    }

    #[On('set-filter-status')]
    public function handleSetFilterStatus(?string $status = null): void
    {
        $this->setFilterStatus($status);
    }

    #[On('set-sort-by')]
    public function handleSetSortBy(?string $sortBy = null): void
    {
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

            $this->getTaskService()->createTask($validated, auth()->id());

            $this->dispatch('notify', message: 'Task created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Task creation validation failed', [
                'user_id' => auth()->id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create task', [
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

            $this->getTaskService()->updateTaskField($task, $field, $value, $instanceDate);

            // No need to dispatch 'task-updated' - Livewire will automatically re-render
            // after this method completes, and computed properties will recompute.
            // We only dispatch 'item-updated' for other components that might listen to it.
            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update task field from parent', [
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

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskTags($model, $tagIds),
                'event' => $this->getEventService()->updateEventTags($model, $tagIds),
                'project' => $this->getProjectService()->updateProjectTags($model, $tagIds),
            };

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update tags from parent', [
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

            $this->getEventService()->updateEventField($event, $field, $value, $instanceDate);

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update event field from parent', [
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

            $this->getEventService()->deleteEvent($event);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Event deleted successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete event from parent', [
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

            $this->getProjectService()->updateProjectField($project, $field, $value);

            $this->dispatch('item-updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update project field from parent', [
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

            $this->getProjectService()->deleteProject($project);

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Project deleted successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete project from parent', [
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

            $this->getEventService()->createEvent($validated, auth()->id());

            $this->dispatch('notify', message: 'Event created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Event creation validation failed', [
                'user_id' => auth()->id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create event', [
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

            $this->getProjectService()->createProject($validated, auth()->id());

            $this->dispatch('notify', message: 'Project created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Project creation validation failed', [
                'user_id' => auth()->id(),
                'validation_errors' => $e->errors(),
                'input_data' => $data,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create project', [
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

    /**
     * @return array{success: bool, message?: string, tagId?: int, tagName?: string, alreadyExists?: bool}
     */
    public function createTag(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'Tag name cannot be empty'];
        }

        try {
            $result = $this->getTagService()->findOrCreateTag($name);
            $tag = $result['tag'];

            if (! in_array($tag->id, $this->filterTagIds, true)) {
                $this->filterTagIds[] = $tag->id;
            }

            return [
                'success' => true,
                'tagId' => $tag->id,
                'tagName' => $tag->name,
                'alreadyExists' => ! $result['wasRecentlyCreated'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create tag', [
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

    /**
     * @return array{success: bool, message?: string}
     */
    public function deleteTag(int $tagId): array
    {
        try {
            $tag = Tag::findOrFail($tagId);
            $this->getTagService()->deleteTag($tag);

            $this->filterTagIds = array_values(
                array_filter($this->filterTagIds, fn ($id) => (int) $id !== $tagId)
            );

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Failed to delete tag', [
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

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskDateTime($model, $newStart, $newEnd),
                'event' => $this->getEventService()->updateEventDateTime($model, $newStart, $newEnd),
                'project' => $this->getProjectService()->updateProjectDateTime($model, $newStart, $newEnd),
            };

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Item updated successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to update item datetime', [
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
            $model = match ($itemType) {
                'task' => Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions', 'recurringTask'])
                    ->findOrFail($itemId),
                'event' => Event::with(['tags', 'reminders', 'recurringEvent'])
                    ->findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskDuration($model, $newDurationMinutes),
                'event' => $this->getEventService()->updateEventDuration($model, $newDurationMinutes),
            };

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Duration updated successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to update item duration', [
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

            match ($itemType) {
                'task' => $this->getTaskService()->updateTaskStatus($model, $newStatus, $instanceDate),
                'event' => $this->getEventService()->updateEventStatus($model, $newStatus, $instanceDate),
                'project' => null, // Projects don't have status
            };

            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to update item status', [
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
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getDateRangeForView(): array
    {
        return $this->getDateRangeForViewTrait($this->viewMode, $this->currentDate, $this->weekStartDate);
    }

    /**
     * Apply filters to a collection of instances.
     * This centralizes the filtering logic used in both filteredTasks() and filteredEvents().
     */
    protected function filterInstances(Collection $instances): Collection
    {
        return $instances->filter(function ($instance): bool {
            // Filter by status if set
            if ($this->filterStatus && $this->filterStatus !== 'all') {
                if ($instance->status?->value !== $this->filterStatus) {
                    return false;
                }
            }

            // Filter by priority if set (for tasks)
            if ($this->filterPriority && $this->filterPriority !== 'all') {
                if (isset($instance->priority) && $instance->priority?->value !== $this->filterPriority) {
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

            // Skip cancelled instances (for events)
            if (isset($instance->cancelled) && $instance->cancelled) {
                return false;
            }

            // For timegrid views, only show items with at least one date
            if (in_array($this->viewMode, ['daily-timegrid', 'weekly-timegrid'], true)) {
                return $instance->start_datetime !== null || $instance->end_datetime !== null;
            }

            return true;
        });
    }

    /**
     * Apply base query filters using the FiltersItems trait.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $itemType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyBaseFilters($query, string $itemType)
    {
        $shouldFilter = match ($itemType) {
            'task' => $this->filterType === 'task' || ! $this->filterType || $this->filterType === 'all',
            'event' => $this->filterType === 'event' || ! $this->filterType || $this->filterType === 'all',
            default => false,
        };

        if (! $shouldFilter) {
            return $query;
        }

        if ($this->filterPriority && $this->filterPriority !== 'all') {
            $query->filterByPriority($this->filterPriority);
        }

        if ($this->filterStatus && $this->filterStatus !== 'all') {
            $query->filterByStatus($this->filterStatus);
        }

        if (! empty($this->filterTagIds)) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
        }

        return $query;
    }

    #[Computed]
    public function filteredTasks(): Collection
    {
        $user = auth()->user();
        $dateRange = $this->getDateRangeForView();

        $query = Task::query()
            ->accessibleBy($user)
            ->with(['project.tasks', 'tags', 'event', 'recurringTask']);

        // Apply base filters using centralized method
        $this->applyBaseFilters($query, 'task');

        // Early return if type filter excludes tasks
        if ($this->filterType && $this->filterType !== 'all' && $this->filterType !== 'task') {
            return collect();
        }

        // Get tasks (date filtering happens at instance level)
        $tasks = $query->get();

        // Generate and filter instances
        $allInstances = collect();
        foreach ($tasks as $task) {
            $instances = $task->getInstancesForDateRange($dateRange['start'], $dateRange['end']);
            $allInstances = $allInstances->merge($instances);
        }

        // Apply instance-level filters
        $filteredInstances = $this->filterInstances($allInstances);

        // Apply sorting and add metadata
        return $this->sortCollection($filteredInstances, $this->sortBy, $this->sortDirection)
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
        $dateRange = $this->getDateRangeForView();

        $query = Event::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks', 'recurringEvent']);

        // Apply base filters using centralized method
        $this->applyBaseFilters($query, 'event');

        // Early return if type filter excludes events
        if ($this->filterType && $this->filterType !== 'all' && $this->filterType !== 'event') {
            return collect();
        }

        // Get events (date filtering happens at instance level)
        $events = $query->get();

        // Generate and filter instances
        $allInstances = collect();
        foreach ($events as $event) {
            $instances = $event->getInstancesForDateRange($dateRange['start'], $dateRange['end']);
            $allInstances = $allInstances->merge($instances);
        }

        // Apply instance-level filters
        $filteredInstances = $this->filterInstances($allInstances);

        // Apply sorting and add metadata
        return $this->sortCollection($filteredInstances, $this->sortBy, $this->sortDirection)
            ->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime ?? $event->created_at;

                return $event;
            });
    }

    #[Computed]
    public function filteredItems(): Collection
    {
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
