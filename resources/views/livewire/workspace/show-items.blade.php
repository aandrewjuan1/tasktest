<?php

use App\Enums\EventStatus;
use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
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
    public string $viewMode = 'list'; // list, kanban, weekly_calendar

    // Weekly calendar view properties
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
        } elseif ($this->viewMode === 'weekly') {
            // For weekly view, update to the start of the week containing the clicked date
            $this->weekStartDate = $parsedDate->copy()->startOfWeek();
        }
    }

    #[On('switch-to-week-view')]
    public function switchToWeekView(string $weekStart): void
    {
        $this->viewMode = 'weekly';
        $this->weekStartDate = Carbon::parse($weekStart);
    }

    #[On('update-item-status')]
    public function handleUpdateItemStatus(int $itemId, string $itemType, string $newStatus): void
    {
        $this->updateItemStatus($itemId, $itemType, $newStatus);
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
    public function handleUpdateTaskField(int $taskId, string $field, mixed $value): void
    {
        $this->updateTaskField($taskId, $field, $value);
    }

    #[On('update-event-field')]
    public function handleUpdateEventField(int $eventId, string $field, mixed $value): void
    {
        $this->updateEventField($eventId, $field, $value);
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
            ], [], [
                'title' => 'title',
                'status' => 'status',
                'priority' => 'priority',
                'complexity' => 'complexity',
                'duration' => 'duration',
                'startDatetime' => 'start datetime',
                'endDatetime' => 'end datetime',
                'projectId' => 'project',
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
            });

            $this->dispatch('notify', message: 'Task created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', message: 'Failed to create task. Please try again.', type: 'error');
        }
    }

    public function updateTaskField(int $taskId, string $field, mixed $value): void
    {
        try {
            $task = Task::with(['project', 'event', 'tags', 'reminders', 'pomodoroSessions'])
                ->findOrFail($taskId);

            $this->authorize('update', $task);

            $validationRules = $this->taskFieldRules($field);

            if (empty($validationRules)) {
                return;
            }

            validator(
                [$field => $value],
                [$field => $validationRules],
                [],
                [$field => $field],
            )->validate();

            DB::transaction(function () use ($task, $field, $value) {
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
                }

                if (! empty($updateData)) {
                    $task->update($updateData);
                    $task->refresh();
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

    public function updateEventField(int $eventId, string $field, mixed $value): void
    {
        try {
            $event = Event::with(['tags', 'reminders'])
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

            DB::transaction(function () use ($event, $field, $value) {
                $updateData = [];

                switch ($field) {
                    case 'title':
                        $updateData['title'] = $value;
                        break;
                    case 'description':
                        $updateData['description'] = $value ?: null;
                        break;
                    case 'startDatetime':
                        $startDatetime = Carbon::parse($value);
                        $updateData['start_datetime'] = $startDatetime;
                        if (! $event->end_datetime) {
                            $updateData['end_datetime'] = $startDatetime->copy()->addHour();
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
                    $event->refresh();
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
                    $project->refresh();
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
            ], [], [
                'title' => 'title',
                'status' => 'status',
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
            });

            $this->dispatch('notify', message: 'Event created successfully', type: 'success');
            $this->dispatch('item-created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
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
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
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
                'name' => $name,
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
                'tagId' => $tagId,
            ]);

            return ['success' => false, 'message' => 'Failed to delete tag'];
        }
    }

    public function updateItemDateTime(int $itemId, string $itemType, string $newStart, ?string $newEnd = null): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::findOrFail($itemId),
                'event' => Event::findOrFail($itemId),
                'project' => Project::findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

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
            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Item updated successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to update item datetime', ['error' => $e->getMessage(), 'itemId' => $itemId, 'itemType' => $itemType]);
            $this->dispatch('notify', message: 'Failed to update item', type: 'error');
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
                'task' => Task::findOrFail($itemId),
                'event' => Event::findOrFail($itemId),
                default => throw new \InvalidArgumentException('Invalid item type'),
            };

            $this->authorize('update', $model);

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
            $this->dispatch('item-updated');
            $this->dispatch('notify', message: 'Duration updated successfully', type: 'success');
        } catch (\Exception $e) {
            \Log::error('Failed to update item duration', ['error' => $e->getMessage(), 'itemId' => $itemId, 'itemType' => $itemType]);
            $this->dispatch('notify', message: 'Failed to update duration', type: 'error');
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
            default => [],
        };
    }

    protected function eventFieldRules(string $field): array
    {
        $statusValues = array_map(fn ($c) => $c->value, EventStatus::cases());

        return match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startDatetime' => ['required', 'date'],
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

    public function updateItemStatus(int $itemId, string $itemType, string $newStatus): void
    {
        try {
            $model = match ($itemType) {
                'task' => Task::findOrFail($itemId),
                'event' => Event::findOrFail($itemId),
                'project' => Project::findOrFail($itemId),
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
                    $model->update(['status' => $statusEnum]);
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
                    $model->update(['status' => $statusEnum]);
                    $this->dispatch('item-updated');
                    $this->dispatch('notify', message: 'Status updated successfully', type: 'success');
                }
            }
            // Projects don't have status, so no update needed
        } catch (\Exception $e) {
            \Log::error('Failed to update item status', ['error' => $e->getMessage(), 'itemId' => $itemId, 'itemType' => $itemType]);
            $this->dispatch('notify', message: 'Failed to update status', type: 'error');
        }
    }

    #[Computed]
    public function filteredTasks(): Collection
    {
        $user = auth()->user();

        $query = Task::query()
            ->accessibleBy($user)
            ->with(['project', 'tags', 'event']);

        // Apply filters
        if ($this->filterType === 'task' || !$this->filterType || $this->filterType === 'all') {
            // Only apply filters if we're showing tasks or all items
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
            // If filtering by type and it's not 'task', return empty collection
            return collect();
        }

        // Apply date filter for list/kanban views
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $query->dateFilter($this->currentDate);
        }

        // Apply sorting
        $query->orderByField($this->sortBy, $this->sortDirection);

        return $query->get()
            ->filter(function ($task) {
                // For kanban and weekly views, only show items with at least one date
                if (in_array($this->viewMode, ['kanban', 'weekly'])) {
                    return $task->start_datetime || $task->end_datetime;
                }
                // For list view, show all items
                return true;
            })
            ->map(function ($task) {
                $task->item_type = 'task';
                $task->sort_date = $task->end_datetime ?? $task->created_at;

                return $task;
            });
    }

    #[Computed]
    public function filteredEvents(): Collection
    {
        $user = auth()->user();

        $query = Event::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks']);

        // Apply filters
        if ($this->filterType === 'event' || !$this->filterType || $this->filterType === 'all') {
            // Only apply filters if we're showing events or all items
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
            // If filtering by type and it's not 'event', return empty collection
            return collect();
        }

        // Apply date filter for list/kanban views
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $query->dateFilter($this->currentDate);
        }

        // Apply sorting
        $query->orderByField($this->sortBy, $this->sortDirection);

        return $query->get()
            ->filter(function ($event) {
                // For kanban and weekly views, only show items with at least one date
                if (in_array($this->viewMode, ['kanban', 'weekly'])) {
                    return $event->start_datetime || $event->end_datetime;
                }
                // For list view, show all items
                return true;
            })
            ->map(function ($event) {
                $event->item_type = 'event';
                $event->sort_date = $event->start_datetime;

                return $event;
            });
    }

    #[Computed]
    public function filteredProjects(): Collection
    {
        $user = auth()->user();

        $query = Project::query()
            ->accessibleBy($user)
            ->with(['tags', 'tasks']);

        // Apply filters
        if ($this->filterType === 'project' || !$this->filterType || $this->filterType === 'all') {
            // Only apply filters if we're showing projects or all items
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
            // If filtering by type and it's not 'project', return empty collection
            return collect();
        }

        // Apply date filter for list/kanban views
        if (in_array($this->viewMode, ['list', 'kanban']) && $this->currentDate) {
            $query->dateFilter($this->currentDate);
        }

        // Apply sorting
        $query->orderByField($this->sortBy, $this->sortDirection);

        return $query->get()
            ->filter(function ($project) {
                // For kanban and weekly views, only show items with at least one date
                if (in_array($this->viewMode, ['kanban', 'weekly'])) {
                    return $project->start_datetime || $project->end_datetime;
                }
                // For list view, show all items
                return true;
            })
            ->map(function ($project) {
                $project->item_type = 'project';
                $project->sort_date = $project->created_at;

                return $project;
            });
    }

    #[Computed]
    public function filteredItems(): Collection
    {
        // For weekly view, apply filters and sorting but not date filtering (weekly view handles its own date filtering)
        if ($this->viewMode === 'weekly') {
            $user = auth()->user();

            // Get filtered tasks (without date filter)
            $taskQuery = Task::query()
                ->accessibleBy($user)
                ->with(['project', 'tags', 'event']);

            if ($this->filterType === 'task' || !$this->filterType || $this->filterType === 'all') {
                if ($this->filterPriority) {
                    $taskQuery->filterByPriority($this->filterPriority);
                }
                if ($this->filterStatus) {
                    $taskQuery->filterByStatus($this->filterStatus);
                }
            if (! empty($this->filterTagIds)) {
                $taskQuery->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
            }
            } else {
                $taskQuery->whereRaw('1 = 0'); // Return empty result
            }

            $taskQuery->orderByField($this->sortBy, $this->sortDirection);
            $tasks = $taskQuery->get()
                ->filter(function ($task) {
                    // For weekly view, only show items with at least one date
                    return $task->start_datetime || $task->end_datetime;
                })
                ->map(function ($task) {
                    $task->item_type = 'task';
                    $task->sort_date = $task->end_datetime ?? $task->created_at;
                    return $task;
                });

            // Get filtered events (without date filter)
            $eventQuery = Event::query()
                ->accessibleBy($user)
                ->with(['tags', 'tasks']);

            if ($this->filterType === 'event' || !$this->filterType || $this->filterType === 'all') {
                if ($this->filterPriority) {
                    $eventQuery->filterByPriority($this->filterPriority);
                }
                if ($this->filterStatus) {
                    $eventQuery->filterByStatus($this->filterStatus);
                }
            if (! empty($this->filterTagIds)) {
                $eventQuery->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
            }
            } else {
                $eventQuery->whereRaw('1 = 0'); // Return empty result
            }

            $eventQuery->orderByField($this->sortBy, $this->sortDirection);
            $events = $eventQuery->get()
                ->filter(function ($event) {
                    // For weekly view, only show items with at least one date
                    return $event->start_datetime || $event->end_datetime;
                })
                ->map(function ($event) {
                    $event->item_type = 'event';
                    $event->sort_date = $event->start_datetime;
                    return $event;
                });

            // Get filtered projects (without date filter)
            $projectQuery = Project::query()
                ->accessibleBy($user)
                ->with(['tags', 'tasks']);

            if ($this->filterType === 'project' || !$this->filterType || $this->filterType === 'all') {
                if ($this->filterPriority) {
                    $projectQuery->filterByPriority($this->filterPriority);
                }
                if ($this->filterStatus) {
                    $projectQuery->filterByStatus($this->filterStatus);
                }
            if (! empty($this->filterTagIds)) {
                $projectQuery->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $this->filterTagIds));
            }
            } else {
                $projectQuery->whereRaw('1 = 0'); // Return empty result
            }

            $projectQuery->orderByField($this->sortBy, $this->sortDirection);
            $projects = $projectQuery->get()
                ->filter(function ($project) {
                    // For weekly view, only show items with at least one date
                    return $project->start_datetime || $project->end_datetime;
                })
                ->map(function ($project) {
                    $project->item_type = 'project';
                    $project->sort_date = $project->created_at;
                    return $project;
                });

            return collect()->merge($tasks)->merge($events)->merge($projects);
        }

        // For list/kanban views, merge filtered collections
        return collect()
            ->merge($this->filteredTasks)
            ->merge($this->filteredEvents)
            ->merge($this->filteredProjects);
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

    <!-- Tag Filter -->
    <div class="flex flex-wrap gap-2 items-center">
        <x-inline-create-dropdown dropdown-class="w-64 max-h-60 overflow-y-auto">
            <x-slot:trigger>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <span class="text-sm font-medium">Tags</span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    @if(count($filterTagIds) > 0)
                        {{ count($filterTagIds) }} selected
                    @else
                        None
                    @endif
                </span>
            </x-slot:trigger>

            <x-slot:options>
                <!-- Add Tag Button -->
                <button
                    x-show="!tagDropdown.showCreateInput"
                    @click.stop="tagDropdown.showCreateInput = true; $nextTick(() => $refs.newTagInput?.focus());"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                    type="button"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Tag</span>
                </button>

                <!-- Create Tag Input -->
                <div
                    x-show="tagDropdown.showCreateInput"
                    x-cloak
                    class="px-4 py-2"
                    @click.stop
                >
                    <div class="flex gap-2 items-center">
                        <input
                            x-ref="newTagInput"
                            type="text"
                            x-model="tagDropdown.newTagName"
                            @keydown.enter.prevent="tagDropdown.handleCreate()"
                            @keydown.escape="tagDropdown.showCreateInput = false; tagDropdown.newTagName = ''"
                            placeholder="Tag name..."
                            class="flex-1 px-2 py-1 text-sm rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            :disabled="tagDropdown.creating"
                        />
                        <button
                            @click.stop="tagDropdown.handleCreate()"
                            :disabled="!tagDropdown.newTagName.trim() || tagDropdown.creating"
                            class="p-1 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            title="Create tag"
                            type="button"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                        <button
                            @click.stop="tagDropdown.showCreateInput = false; tagDropdown.newTagName = ''"
                            :disabled="tagDropdown.creating"
                            class="p-1 text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            title="Cancel"
                            type="button"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Clear Selected -->
                <button
                    x-show="{{ count($filterTagIds) }} > 0"
                    @click.stop="$wire.filterTagIds = []"
                    class="w-full flex items-center justify-between px-4 py-1.5 text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                    type="button"
                >
                    <span>Clear selected</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Tags List -->
                @foreach($this->availableTags as $tag)
                    <div
                        wire:key="filter-tag-{{ $tag->id }}"
                        class="flex items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 group"
                    >
                        <label class="flex items-center flex-1 cursor-pointer">
                            <input
                                type="checkbox"
                                class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                value="{{ $tag->id }}"
                                wire:model.live="filterTagIds"
                            />
                            <span class="ml-2 text-sm flex-1">{{ $tag->name }}</span>
                        </label>
                        <button
                            @click.stop="tagDropdown.handleDelete({{ $tag->id }})"
                            :disabled="tagDropdown.deleting"
                            class="ml-2 p-1 opacity-0 group-hover:opacity-100 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Delete tag"
                            type="button"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endforeach

                @if($this->availableTags->isEmpty())
                    <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400">No tags available</div>
                @endif
            </x-slot:options>
        </x-inline-create-dropdown>
    </div>

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

    <!-- Weekly Timegrid View -->
    @if($viewMode === 'weekly')
        <div wire:key="weekly-view-container-{{ $weekStartDate->format('Y-m-d') }}">
            <livewire:workspace.weekly-view
                :items="$this->filteredItems"
                :week-start-date="$weekStartDate"
                :view-mode="$viewMode"
                :filter-type="$filterType"
                :filter-priority="$filterPriority"
                :filter-status="$filterStatus"
                :sort-by="$sortBy"
                :sort-direction="$sortDirection"
                :has-active-filters="$this->hasActiveFilters"
                wire:key="weekly-view-{{ $weekStartDate->format('Y-m-d') }}"
            />
        </div>
    @endif

    <!-- Create Item -->
    <livewire:workspace.create-item />
</div>
